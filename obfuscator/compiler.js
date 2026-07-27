// ============================================================================
// 2T1 EPSILON — REAL LUA BYTECODE COMPILER
// Compiles Lua source code into custom bytecode via AST transformation.
// This compiler outputs register-based instructions that are executed by
// a custom VM interpreter — loadstring is NEVER called.
// ============================================================================

const luaparse = require('luaparse');

// ─── Opcode Definitions ─────────────────────────────────────────────────────
const OP = {
    LOADK: 1, LOADNIL: 2, LOADBOOL: 3, MOVE: 4,
    GETGLOBAL: 5, SETGLOBAL: 6, GETTABLE: 7, SETTABLE: 8, NEWTABLE: 9,
    ADD: 10, SUB: 11, MUL: 12, DIV: 13, MOD: 14, POW: 15,
    CONCAT: 16, UNM: 17, NOT: 18, LEN: 19,
    EQ: 20, LT: 21, LE: 22, JMP: 23, TEST: 24,
    CALL: 25, RETURN: 26,
    FORPREP: 27, FORLOOP: 28, TFORLOOP: 29, SETLIST: 30,
    CLOSURE: 31, GETUPVAL: 32, SETUPVAL: 33, SELF: 34,
    VARARG: 35, TESTSET: 36,
};

const BINOP_MAP = {
    '+': OP.ADD, '-': OP.SUB, '*': OP.MUL, '/': OP.DIV,
    '%': OP.MOD, '^': OP.POW, '..': OP.CONCAT,
};

// ─── Compiler Class ─────────────────────────────────────────────────────────
class Compiler {
    constructor(parent = null) {
        this.code = [];
        this.constants = [];
        this.prototypes = [];
        this.locals = [];       // [{name, depth, reg, isCaptured}]
        this.upvalues = [];     // [{name, isLocal, index}]
        this.scopeDepth = 0;
        this.freereg = 0;
        this.maxreg = 0;
        this.numParams = 0;
        this.isVararg = false;
        this.parent = parent;
        this.loopBreaks = [];   // stack of arrays for break jump indices
    }

    // ── Constants ──
    addConstant(value) {
        for (let i = 0; i < this.constants.length; i++) {
            const c = this.constants[i];
            if (c === value && typeof c === typeof value) return i;
            if (value === null && c === null) return i;
        }
        this.constants.push(value);
        return this.constants.length - 1;
    }

    RK(constIndex) {
        return constIndex + 256;
    }

    // ── Registers ──
    allocReg() {
        const r = this.freereg++;
        if (this.freereg > this.maxreg) this.maxreg = this.freereg;
        return r;
    }

    freeReg() {
        if (this.freereg > 0) this.freereg--;
    }

    freeRegs(n) {
        this.freereg -= n;
        if (this.freereg < 0) this.freereg = 0;
    }

    reserveRegs(n) {
        this.freereg += n;
        if (this.freereg > this.maxreg) this.maxreg = this.freereg;
    }

    // ── Code Emission ──
    emit(op, a = 0, b = 0, c = 0) {
        const pos = this.code.length;
        this.code.push(op, a, b, c);
        return pos;  // instruction position (index of opcode)
    }

    // Emit a JMP and return the position for patching
    emitJmp(offset = 0) {
        return this.emit(OP.JMP, offset);
    }

    // Patch a JMP instruction to jump to current position
    patchJmp(pos) {
        // pos is the index of the JMP opcode in code[]
        // A field is at pos+1
        // Target = current instruction index
        const currentInst = this.code.length / 4;
        const jmpInst = pos / 4;
        this.code[pos + 1] = currentInst - jmpInst - 1;
    }

    currentPC() {
        return this.code.length / 4;
    }

    // ── Scope ──
    beginScope() {
        this.scopeDepth++;
    }

    endScope() {
        // Remove locals from this scope
        while (this.locals.length > 0 &&
               this.locals[this.locals.length - 1].depth === this.scopeDepth) {
            const local = this.locals.pop();
            if (this.freereg > local.reg) {
                this.freereg = local.reg;
            }
        }
        this.scopeDepth--;
    }

    addLocal(name) {
        const reg = this.allocReg();
        this.locals.push({ name, depth: this.scopeDepth, reg, isCaptured: false });
        return reg;
    }

    resolveLocal(name) {
        for (let i = this.locals.length - 1; i >= 0; i--) {
            if (this.locals[i].name === name) return this.locals[i].reg;
        }
        return -1;
    }

    resolveUpvalue(name) {
        // Check if already resolved
        for (let i = 0; i < this.upvalues.length; i++) {
            if (this.upvalues[i].name === name) return i;
        }

        // Check parent locals
        if (this.parent) {
            const localReg = this.parent.resolveLocal(name);
            if (localReg !== -1) {
                // Mark as captured in parent
                for (const l of this.parent.locals) {
                    if (l.name === name) l.isCaptured = true;
                }
                this.upvalues.push({ name, isLocal: true, index: localReg });
                return this.upvalues.length - 1;
            }

            // Check parent upvalues (recursively)
            const upIdx = this.parent.resolveUpvalue(name);
            if (upIdx !== -1) {
                this.upvalues.push({ name, isLocal: false, index: upIdx });
                return this.upvalues.length - 1;
            }
        }

        return -1;
    }

    // ── Statement Compilation ──
    compileChunk(body) {
        for (const stmt of body) {
            this.compileStatement(stmt);
        }
    }

    compileStatement(node) {
        switch (node.type) {
            case 'LocalStatement': return this.compileLocalStatement(node);
            case 'AssignmentStatement': return this.compileAssignmentStatement(node);
            case 'CallStatement': return this.compileCallStatement(node);
            case 'IfStatement': return this.compileIfStatement(node);
            case 'WhileStatement': return this.compileWhileStatement(node);
            case 'DoStatement': return this.compileDoStatement(node);
            case 'RepeatStatement': return this.compileRepeatStatement(node);
            case 'ForNumericStatement': return this.compileForNumericStatement(node);
            case 'ForGenericStatement': return this.compileForGenericStatement(node);
            case 'ReturnStatement': return this.compileReturnStatement(node);
            case 'BreakStatement': return this.compileBreakStatement(node);
            case 'FunctionDeclaration': return this.compileFunctionDeclaration(node);
            default:
                // Skip comments and unknown nodes
                break;
        }
    }

    compileLocalStatement(node) {
        const numVars = node.variables.length;
        const numInits = node.init.length;
        const regs = [];

        // Compile initializers first (before adding locals, to handle self-reference correctly)
        const initRegs = [];
        for (let i = 0; i < numInits; i++) {
            const isLast = (i === numInits - 1);
            if (isLast && i < numVars - 1 && this.isMultiReturnExpr(node.init[i])) {
                // Last init is multi-return and we need more values
                const base = this.freereg;
                const needed = numVars - i;
                this.compileExpr(node.init[i], base, needed);
                for (let j = 0; j < needed; j++) {
                    initRegs.push(base + j);
                }
                this.freereg = base + needed;
                if (this.freereg > this.maxreg) this.maxreg = this.freereg;
                break;
            } else {
                const reg = this.freereg;
                this.compileExpr(node.init[i], reg, 1);
                initRegs.push(reg);
                if (this.freereg <= reg) {
                    this.freereg = reg + 1;
                    if (this.freereg > this.maxreg) this.maxreg = this.freereg;
                }
            }
        }

        // Reset freereg to before inits
        if (initRegs.length > 0) {
            this.freereg = initRegs[0];
        }

        // Now add locals and move init values
        for (let i = 0; i < numVars; i++) {
            const name = node.variables[i].name;
            const localReg = this.addLocal(name);

            if (i < initRegs.length) {
                if (initRegs[i] !== localReg) {
                    this.emit(OP.MOVE, localReg, initRegs[i]);
                }
            } else {
                this.emit(OP.LOADNIL, localReg, 0);
            }
        }
    }

    compileAssignmentStatement(node) {
        const numVars = node.variables.length;
        const numInits = node.init.length;

        // Compile all right-hand side values first
        const rhsBase = this.freereg;
        for (let i = 0; i < numInits; i++) {
            const isLast = (i === numInits - 1);
            if (isLast && i < numVars - 1 && this.isMultiReturnExpr(node.init[i])) {
                const needed = numVars - i;
                this.compileExpr(node.init[i], this.freereg, needed);
                this.freereg += needed;
                if (this.freereg > this.maxreg) this.maxreg = this.freereg;
                break;
            } else {
                this.compileExpr(node.init[i], this.freereg, 1);
                this.freereg++;
                if (this.freereg > this.maxreg) this.maxreg = this.freereg;
            }
        }

        // Assign each variable
        for (let i = 0; i < numVars; i++) {
            const rhsReg = rhsBase + i;
            const lhs = node.variables[i];

            if (lhs.type === 'Identifier') {
                const localReg = this.resolveLocal(lhs.name);
                if (localReg !== -1) {
                    if (rhsReg !== localReg) {
                        this.emit(OP.MOVE, localReg, rhsReg);
                    }
                } else {
                    const upIdx = this.resolveUpvalue(lhs.name);
                    if (upIdx !== -1) {
                        this.emit(OP.SETUPVAL, rhsReg, upIdx);
                    } else {
                        const ki = this.addConstant(lhs.name);
                        this.emit(OP.SETGLOBAL, rhsReg, ki);
                    }
                }
            } else if (lhs.type === 'MemberExpression') {
                const tableReg = this.freereg;
                this.compileExpr(lhs.base, tableReg, 1);
                this.freereg = tableReg + 1;
                if (this.freereg > this.maxreg) this.maxreg = this.freereg;
                const ki = this.addConstant(lhs.identifier.name);
                this.emit(OP.SETTABLE, tableReg, this.RK(ki), rhsReg < 256 ? rhsReg : this.RK(rhsReg));
                this.freereg = tableReg;
            } else if (lhs.type === 'IndexExpression') {
                const tableReg = this.freereg;
                this.compileExpr(lhs.base, tableReg, 1);
                this.freereg = tableReg + 1;
                const keyReg = this.freereg;
                this.compileExpr(lhs.index, keyReg, 1);
                this.freereg = keyReg + 1;
                if (this.freereg > this.maxreg) this.maxreg = this.freereg;
                this.emit(OP.SETTABLE, tableReg, keyReg, rhsReg);
                this.freereg = tableReg;
            }
        }

        this.freereg = rhsBase;
    }

    compileCallStatement(node) {
        const expr = node.expression;
        const base = this.freereg;
        this.compileCallExpr(expr, base, 1);  // 1 = discard results
        this.freereg = base;
    }

    compileIfStatement(node) {
        const exitJumps = [];

        for (let i = 0; i < node.clauses.length; i++) {
            const clause = node.clauses[i];

            if (clause.type === 'IfClause' || clause.type === 'ElseifClause') {
                const condReg = this.freereg;
                this.compileExpr(clause.condition, condReg, 1);
                this.freereg = condReg;

                this.emit(OP.TEST, condReg, 0, 0);
                const skipJmp = this.emitJmp();

                this.beginScope();
                this.compileChunk(clause.body);
                this.endScope();

                if (i < node.clauses.length - 1) {
                    exitJumps.push(this.emitJmp());
                }

                this.patchJmp(skipJmp);

            } else if (clause.type === 'ElseClause') {
                this.beginScope();
                this.compileChunk(clause.body);
                this.endScope();
            }
        }

        for (const j of exitJumps) {
            this.patchJmp(j);
        }
    }

    compileWhileStatement(node) {
        const loopStart = this.currentPC();

        this.loopBreaks.push([]);

        const condReg = this.freereg;
        this.compileExpr(node.condition, condReg, 1);
        this.freereg = condReg;

        this.emit(OP.TEST, condReg, 0, 0);
        const exitJmp = this.emitJmp();

        this.beginScope();
        this.compileChunk(node.body);
        this.endScope();

        // Jump back to loop start
        const backOffset = loopStart - this.currentPC() - 1;
        this.emit(OP.JMP, backOffset);

        this.patchJmp(exitJmp);

        // Patch all breaks
        const breaks = this.loopBreaks.pop();
        for (const b of breaks) {
            this.patchJmp(b);
        }
    }

    compileDoStatement(node) {
        this.beginScope();
        this.compileChunk(node.body);
        this.endScope();
    }

    compileRepeatStatement(node) {
        const loopStart = this.currentPC();
        this.loopBreaks.push([]);

        this.beginScope();
        this.compileChunk(node.body);

        const condReg = this.freereg;
        this.compileExpr(node.condition, condReg, 1);
        this.freereg = condReg;

        this.emit(OP.TEST, condReg, 0, 0);
        const backOffset = loopStart - this.currentPC() - 1;
        this.emit(OP.JMP, backOffset);

        this.endScope();

        const breaks = this.loopBreaks.pop();
        for (const b of breaks) {
            this.patchJmp(b);
        }
    }

    compileForNumericStatement(node) {
        this.beginScope();
        this.loopBreaks.push([]);

        // Allocate 3 internal slots: index, limit, step
        const base = this.freereg;

        // Compile start, limit, step
        this.compileExpr(node.start, base, 1);
        this.freereg = base + 1;
        this.compileExpr(node.end, base + 1, 1);
        this.freereg = base + 2;
        if (node.step) {
            this.compileExpr(node.step, base + 2, 1);
        } else {
            const ki = this.addConstant(1);
            this.emit(OP.LOADK, base + 2, ki);
        }
        this.freereg = base + 3;
        if (this.freereg > this.maxreg) this.maxreg = this.freereg;

        // FORPREP: base -= step, jump to FORLOOP test
        const prepPos = this.emit(OP.FORPREP, base, 0);

        // Loop variable in base+3
        const varReg = base + 3;
        this.freereg = varReg + 1;
        if (this.freereg > this.maxreg) this.maxreg = this.freereg;
        this.locals.push({ name: node.variable.name, depth: this.scopeDepth, reg: varReg, isCaptured: false });

        // Body
        const loopBodyStart = this.currentPC();
        this.compileChunk(node.body);

        // Patch FORPREP to jump here (to FORLOOP)
        this.code[prepPos + 2] = this.currentPC() - (prepPos / 4) - 1;

        // FORLOOP: increment and test, jump back to body
        const backOffset = loopBodyStart - this.currentPC() - 1;
        this.emit(OP.FORLOOP, base, backOffset);

        // Patch breaks
        const breaks = this.loopBreaks.pop();
        for (const b of breaks) { this.patchJmp(b); }

        this.endScope();
        this.freereg = base;
    }

    compileForGenericStatement(node) {
        this.beginScope();
        this.loopBreaks.push([]);

        const base = this.freereg;

        // Compile iterators (f, s, var) into base, base+1, base+2
        for (let i = 0; i < node.iterators.length && i < 3; i++) {
            this.compileExpr(node.iterators[i], base + i, 1);
        }
        // Fill remaining with nil
        for (let i = node.iterators.length; i < 3; i++) {
            this.emit(OP.LOADNIL, base + i, 0);
        }
        this.freereg = base + 3;
        if (this.freereg > this.maxreg) this.maxreg = this.freereg;

        // Jump to the TFORLOOP instruction
        const jmpToTest = this.emitJmp();

        // Loop body starts here
        const bodyStart = this.currentPC();

        // Declare loop variables at base+3, base+4, ...
        const numVars = node.variables.length;
        for (let i = 0; i < numVars; i++) {
            const varReg = base + 3 + i;
            this.freereg = varReg + 1;
            if (this.freereg > this.maxreg) this.maxreg = this.freereg;
            this.locals.push({ name: node.variables[i].name, depth: this.scopeDepth, reg: varReg, isCaptured: false });
        }

        this.compileChunk(node.body);

        // TFORLOOP: call iterator, check results
        this.patchJmp(jmpToTest);
        this.emit(OP.TFORLOOP, base, numVars);

        // If result is not nil, jump back to body
        const backOffset = bodyStart - this.currentPC() - 1;
        this.emit(OP.JMP, backOffset);

        const breaks = this.loopBreaks.pop();
        for (const b of breaks) { this.patchJmp(b); }

        this.endScope();
        this.freereg = base;
    }

    compileReturnStatement(node) {
        const args = node.arguments || [];
        if (args.length === 0) {
            this.emit(OP.RETURN, 0, 1);
        } else {
            const base = this.freereg;
            for (let i = 0; i < args.length; i++) {
                const isLast = (i === args.length - 1);
                if (isLast && this.isMultiReturnExpr(args[i])) {
                    this.compileExpr(args[i], this.freereg, 0); // 0 = return all
                    this.emit(OP.RETURN, base, 0);
                    return;
                }
                this.compileExpr(args[i], this.freereg, 1);
                this.freereg++;
                if (this.freereg > this.maxreg) this.maxreg = this.freereg;
            }
            this.emit(OP.RETURN, base, args.length + 1);
            this.freereg = base;
        }
    }

    compileBreakStatement(_node) {
        if (this.loopBreaks.length === 0) return;
        this.loopBreaks[this.loopBreaks.length - 1].push(this.emitJmp());
    }

    compileFunctionDeclaration(node) {
        if (node.isLocal) {
            // local function name(...) end
            const localReg = this.addLocal(node.identifier.name);
            const protoIdx = this.compileFunction(node);
            this.emit(OP.CLOSURE, localReg, protoIdx);
        } else if (node.identifier) {
            if (node.identifier.type === 'Identifier') {
                const reg = this.freereg;
                const protoIdx = this.compileFunction(node);
                this.emit(OP.CLOSURE, reg, protoIdx);
                const localReg = this.resolveLocal(node.identifier.name);
                if (localReg !== -1) {
                    this.emit(OP.MOVE, localReg, reg);
                } else {
                    const ki = this.addConstant(node.identifier.name);
                    this.emit(OP.SETGLOBAL, reg, ki);
                }
            } else if (node.identifier.type === 'MemberExpression') {
                const tableReg = this.freereg;
                this.compileExpr(node.identifier.base, tableReg, 1);
                this.freereg = tableReg + 1;
                const funcReg = this.freereg;
                const protoIdx = this.compileFunction(node);
                this.emit(OP.CLOSURE, funcReg, protoIdx);
                this.freereg = funcReg + 1;
                if (this.freereg > this.maxreg) this.maxreg = this.freereg;
                const ki = this.addConstant(node.identifier.identifier.name);
                this.emit(OP.SETTABLE, tableReg, this.RK(ki), funcReg);
                this.freereg = tableReg;
            }
        }
    }

    compileFunction(node) {
        const child = new Compiler(this);
        child.beginScope();

        // Handle 'self' parameter for method definitions
        if (node.identifier && node.identifier.type === 'MemberExpression' &&
            node.identifier.indexer === ':') {
            child.addLocal('self');
            child.numParams++;
        }

        // Parameters
        for (const param of node.parameters) {
            if (param.type === 'Identifier') {
                child.addLocal(param.name);
                child.numParams++;
            } else if (param.type === 'VarargLiteral') {
                child.isVararg = true;
            }
        }

        // Body
        child.compileChunk(node.body);

        // Implicit return
        child.emit(OP.RETURN, 0, 1);

        child.endScope();

        const proto = {
            code: child.code,
            constants: child.constants,
            prototypes: child.prototypes,
            numParams: child.numParams,
            isVararg: child.isVararg,
            maxStackSize: child.maxreg + 2,
            upvalues: child.upvalues,
        };

        this.prototypes.push(proto);
        return this.prototypes.length - 1;
    }

    // ── Expression Compilation ──
    // Compiles an expression, placing the result into `target` register.
    // `want`: how many results we want (0 = all, 1 = exactly one)
    compileExpr(node, target, want = 1) {
        switch (node.type) {
            case 'NumericLiteral': {
                const ki = this.addConstant(node.value);
                this.emit(OP.LOADK, target, ki);
                break;
            }
            case 'StringLiteral': {
                const val = node.value !== undefined ? node.value :
                            (node.raw ? node.raw.slice(1, -1) : '');
                const ki = this.addConstant(val);
                this.emit(OP.LOADK, target, ki);
                break;
            }
            case 'BooleanLiteral': {
                this.emit(OP.LOADBOOL, target, node.value ? 1 : 0, 0);
                break;
            }
            case 'NilLiteral': {
                this.emit(OP.LOADNIL, target, 0);
                break;
            }
            case 'VarargLiteral': {
                this.emit(OP.VARARG, target, want === 0 ? 0 : want + 1);
                break;
            }
            case 'Identifier': {
                const localReg = this.resolveLocal(node.name);
                if (localReg !== -1) {
                    if (localReg !== target) {
                        this.emit(OP.MOVE, target, localReg);
                    }
                } else {
                    const upIdx = this.resolveUpvalue(node.name);
                    if (upIdx !== -1) {
                        this.emit(OP.GETUPVAL, target, upIdx);
                    } else {
                        const ki = this.addConstant(node.name);
                        this.emit(OP.GETGLOBAL, target, ki);
                    }
                }
                break;
            }
            case 'BinaryExpression': {
                this.compileBinaryExpr(node, target);
                break;
            }
            case 'LogicalExpression': {
                this.compileLogicalExpr(node, target);
                break;
            }
            case 'UnaryExpression': {
                this.compileUnaryExpr(node, target);
                break;
            }
            case 'CallExpression':
            case 'StringCallExpression': {
                this.compileCallExpr(node, target, want === 0 ? 0 : want + 1);
                break;
            }
            case 'MemberExpression': {
                this.compileMemberExpr(node, target);
                break;
            }
            case 'IndexExpression': {
                this.compileIndexExpr(node, target);
                break;
            }
            case 'TableConstructorExpression': {
                this.compileTableConstructor(node, target);
                break;
            }
            case 'FunctionDeclaration': {
                const protoIdx = this.compileFunction(node);
                this.emit(OP.CLOSURE, target, protoIdx);
                break;
            }
            default:
                this.emit(OP.LOADNIL, target, 0);
                break;
        }
    }

    compileBinaryExpr(node, target) {
        const op = BINOP_MAP[node.operator];
        if (op) {
            if (op === OP.CONCAT) {
                // Concatenation: compile left and right into consecutive regs
                const left = target;
                this.compileExpr(node.left, left, 1);
                const right = left + 1;
                this.freereg = right;
                this.compileExpr(node.right, right, 1);
                this.freereg = right + 1;
                if (this.freereg > this.maxreg) this.maxreg = this.freereg;
                this.emit(OP.CONCAT, target, left, right);
                this.freereg = left + 1;
                return;
            }

            // Arithmetic
            const leftRK = this.compileExprToRK(node.left, target);
            const rightRK = this.compileExprToRK(node.right, target + 1);
            this.emit(op, target, leftRK, rightRK);
            return;
        }

        // Comparison operators
        let cmpOp, invert = false;
        switch (node.operator) {
            case '==': cmpOp = OP.EQ; break;
            case '~=': cmpOp = OP.EQ; invert = true; break;
            case '<':  cmpOp = OP.LT; break;
            case '>':  cmpOp = OP.LT; invert = true; 
                // a > b  <==>  b < a — swap operands
                [node.left, node.right] = [node.right, node.left];
                break;
            case '<=': cmpOp = OP.LE; break;
            case '>=': cmpOp = OP.LE; invert = true;
                [node.left, node.right] = [node.right, node.left];
                break;
            default: 
                this.emit(OP.LOADNIL, target, 0);
                return;
        }

        const leftRK = this.compileExprToRK(node.left, target);
        const rightRK = this.compileExprToRK(node.right, target + 1);
        this.emit(cmpOp, invert ? 0 : 1, leftRK, rightRK);
        const skipJmp = this.emitJmp();
        this.emit(OP.LOADBOOL, target, 0, 1);  // false, skip next
        this.patchJmp(skipJmp);
        this.emit(OP.LOADBOOL, target, 1, 0);  // true
    }

    compileLogicalExpr(node, target) {
        this.compileExpr(node.left, target, 1);

        if (node.operator === 'and') {
            // If left is falsy, result is left (already in target). Otherwise, result is right.
            this.emit(OP.TESTSET, target, target, 0);
        } else {
            // 'or': If left is truthy, result is left. Otherwise, result is right.
            this.emit(OP.TESTSET, target, target, 1);
        }
        const skipJmp = this.emitJmp();
        this.compileExpr(node.right, target, 1);
        this.patchJmp(skipJmp);
    }

    compileUnaryExpr(node, target) {
        switch (node.operator) {
            case '-': {
                this.compileExpr(node.argument, target, 1);
                this.emit(OP.UNM, target, target);
                break;
            }
            case 'not': {
                this.compileExpr(node.argument, target, 1);
                this.emit(OP.NOT, target, target);
                break;
            }
            case '#': {
                this.compileExpr(node.argument, target, 1);
                this.emit(OP.LEN, target, target);
                break;
            }
            default:
                this.emit(OP.LOADNIL, target, 0);
        }
    }

    compileCallExpr(node, base, wantResults) {
        let isMethod = false;

        if (node.type === 'StringCallExpression') {
            // func "string" => func("string")
            this.compileExpr(node.base, base, 1);
            this.freereg = base + 1;
            this.compileExpr(node.argument, base + 1, 1);
            this.freereg = base + 2;
            if (this.freereg > this.maxreg) this.maxreg = this.freereg;
            this.emit(OP.CALL, base, 2, wantResults);
            return;
        }

        // Check for method call (base:method(args))
        if (node.base.type === 'MemberExpression' && node.base.indexer === ':') {
            isMethod = true;
            const tableReg = base;
            this.compileExpr(node.base.base, tableReg, 1);
            this.freereg = tableReg + 1;
            if (this.freereg > this.maxreg) this.maxreg = this.freereg;
            const methodKi = this.addConstant(node.base.identifier.name);
            this.emit(OP.SELF, base, tableReg, this.RK(methodKi));
            this.freereg = base + 2;
            if (this.freereg > this.maxreg) this.maxreg = this.freereg;
        } else {
            this.compileExpr(node.base, base, 1);
            this.freereg = base + 1;
            if (this.freereg > this.maxreg) this.maxreg = this.freereg;
        }

        // Compile arguments
        const argBase = isMethod ? base + 2 : base + 1;
        const args = node.arguments || [];
        let numArgs = args.length + (isMethod ? 1 : 0);
        let lastIsMulti = false;

        for (let i = 0; i < args.length; i++) {
            const isLast = (i === args.length - 1);
            if (isLast && this.isMultiReturnExpr(args[i])) {
                this.compileExpr(args[i], argBase + i, 0);
                lastIsMulti = true;
            } else {
                this.compileExpr(args[i], argBase + i, 1);
            }
            this.freereg = argBase + i + 1;
            if (this.freereg > this.maxreg) this.maxreg = this.freereg;
        }

        const B = lastIsMulti ? 0 : numArgs + 1;
        this.emit(OP.CALL, base, B, wantResults);
        this.freereg = base + 1;
    }

    compileMemberExpr(node, target) {
        this.compileExpr(node.base, target, 1);
        const ki = this.addConstant(node.identifier.name);
        this.emit(OP.GETTABLE, target, target, this.RK(ki));
    }

    compileIndexExpr(node, target) {
        const tableReg = target;
        this.compileExpr(node.base, tableReg, 1);
        const keyRK = this.compileExprToRK(node.index, tableReg + 1);
        this.emit(OP.GETTABLE, target, tableReg, keyRK);
    }

    compileTableConstructor(node, target) {
        this.emit(OP.NEWTABLE, target, 0, 0);
        let arrayCount = 0;
        const oldFree = this.freereg;
        this.freereg = target + 1;
        if (this.freereg > this.maxreg) this.maxreg = this.freereg;

        for (const field of node.fields) {
            if (field.type === 'TableKeyString') {
                const ki = this.addConstant(field.key.name);
                const valReg = this.freereg;
                this.compileExpr(field.value, valReg, 1);
                this.freereg = valReg + 1;
                if (this.freereg > this.maxreg) this.maxreg = this.freereg;
                this.emit(OP.SETTABLE, target, this.RK(ki), valReg);
                this.freereg = valReg;
            } else if (field.type === 'TableKey') {
                const keyReg = this.freereg;
                this.compileExpr(field.key, keyReg, 1);
                this.freereg = keyReg + 1;
                const valReg = this.freereg;
                this.compileExpr(field.value, valReg, 1);
                this.freereg = valReg + 1;
                if (this.freereg > this.maxreg) this.maxreg = this.freereg;
                this.emit(OP.SETTABLE, target, keyReg, valReg);
                this.freereg = keyReg;
            } else if (field.type === 'TableValue') {
                arrayCount++;
                const valReg = this.freereg;
                this.compileExpr(field.value, valReg, 1);
                this.freereg = valReg + 1;
                if (this.freereg > this.maxreg) this.maxreg = this.freereg;

                if (arrayCount % 50 === 0 || field === node.fields[node.fields.length - 1]) {
                    this.emit(OP.SETLIST, target, arrayCount % 50 === 0 ? 50 : arrayCount % 50,
                              Math.ceil(arrayCount / 50));
                    this.freereg = target + 1;
                }
            }
        }

        if (arrayCount > 0 && arrayCount % 50 !== 0) {
            // Handled in the loop above
        }

        this.freereg = Math.max(oldFree, target + 1);
    }

    // ── Helpers ──
    compileExprToRK(node, tempReg) {
        // Try to encode as a constant (RK)
        if (node.type === 'NumericLiteral') {
            return this.RK(this.addConstant(node.value));
        }
        if (node.type === 'StringLiteral') {
            const val = node.value !== undefined ? node.value :
                        (node.raw ? node.raw.slice(1, -1) : '');
            return this.RK(this.addConstant(val));
        }
        if (node.type === 'BooleanLiteral') {
            return this.RK(this.addConstant(node.value));
        }
        if (node.type === 'NilLiteral') {
            return this.RK(this.addConstant(null));
        }

        // Otherwise, compile to a temp register
        const saveFree = this.freereg;
        this.freereg = tempReg;
        this.compileExpr(node, tempReg, 1);
        this.freereg = Math.max(saveFree, tempReg + 1);
        if (this.freereg > this.maxreg) this.maxreg = this.freereg;
        return tempReg;
    }

    isMultiReturnExpr(node) {
        return node.type === 'CallExpression' ||
               node.type === 'StringCallExpression' ||
               node.type === 'VarargLiteral';
    }
}


// ─── Public API ─────────────────────────────────────────────────────────────

function compile(source) {
    const ast = luaparse.parse(source, {
        locations: false,
        ranges: false,
        luaVersion: '5.1',
        comments: false,
    });

    const compiler = new Compiler();
    compiler.isVararg = true;
    compiler.beginScope();
    compiler.compileChunk(ast.body);
    compiler.emit(OP.RETURN, 0, 1);
    compiler.endScope();

    return {
        code: compiler.code,
        constants: compiler.constants,
        prototypes: compiler.prototypes,
        numParams: 0,
        isVararg: true,
        maxStackSize: compiler.maxreg + 2,
        upvalues: compiler.upvalues,
    };
}

module.exports = { compile, OP };

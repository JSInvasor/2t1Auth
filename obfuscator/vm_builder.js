const fs = require('fs');
const { compile } = require('./compiler.js');

function serializeProto(proto) {
    const bytes = [];

    function writeByte(b) {
        bytes.push(b & 0xFF);
    }

    function writeInt(i) {
        bytes.push(i & 0xFF);
        bytes.push((i >> 8) & 0xFF);
        bytes.push((i >> 16) & 0xFF);
        bytes.push((i >> 24) & 0xFF);
    }

    function writeString(s) {
        const len = Buffer.byteLength(s, 'utf8');
        writeInt(len);
        for (let i = 0; i < len; i++) {
            bytes.push(s.charCodeAt(i) & 0xFF);
        }
    }

    writeByte(proto.numParams || 0);
    writeByte(proto.isVararg ? 1 : 0);
    writeByte(proto.maxStackSize || 10);

    const code = proto.code || [];
    writeInt(code.length);
    for (let i = 0; i < code.length; i++) {
        writeInt(code[i]);
    }

    const consts = proto.constants || [];
    writeInt(consts.length);
    for (let i = 0; i < consts.length; i++) {
        const c = consts[i];
        if (c === null || c === undefined) {
            writeByte(0);
        } else if (typeof c === 'boolean') {
            writeByte(1);
            writeByte(c ? 1 : 0);
        } else if (typeof c === 'number') {
            writeByte(2);
            writeString(c.toString());
        } else if (typeof c === 'string') {
            writeByte(3);
            writeString(c);
        }
    }

    const protos = proto.prototypes || [];
    writeInt(protos.length);
    for (let i = 0; i < protos.length; i++) {
        const pBytes = serializeProto(protos[i]);
        for (let j = 0; j < pBytes.length; j++) {
            writeByte(pBytes[j]);
        }
    }

    return bytes;
}

function encrypt(data) {
    const key = Math.floor(Math.random() * 255);
    const subMap = Array.from({length: 256}, (_, i) => i);
    // Shuffle subMap for layer 4
    for(let i = 255; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        const temp = subMap[i];
        subMap[i] = subMap[j];
        subMap[j] = temp;
    }
    const invSubMap = [];
    for(let i=0; i<256; i++) invSubMap[subMap[i]] = i;

    const res = [];
    for (let i = 0; i < data.length; i++) {
        let b = data[i];
        // Layer 1: XOR with key
        b = b ^ key;
        // Layer 2: Bit rotation (left by 3)
        b = ((b << 3) & 255) | (b >> 5);
        // Layer 3: Position dependent XOR
        b = b ^ ((i + 1) & 255); // 1-based indexing for lua
        // Layer 4: Byte Substitution
        b = invSubMap[b];
        res.push(b);
    }
    return { encryptedData: res, key, subMap };
}

function generateIllusionNames(count) {
    const chars = ['I', 'l', '1'];
    const names = new Set();
    while (names.size < count) {
        let name = '';
        const len = 8 + Math.floor(Math.random() * 8);
        for (let i = 0; i < len; i++) {
            name += chars[Math.floor(Math.random() * chars.length)];
        }
        // ensure valid lua identifier (starts with letter)
        if (name.startsWith('1')) {
            name = (Math.random() > 0.5 ? 'I' : 'l') + name.substring(1);
        }
        names.add(name);
    }
    return Array.from(names);
}

function obfuscateLua(luaCode) {
    const variables = new Set([...luaCode.matchAll(/_V_[a-zA-Z0-9_]+/g)].map(m => m[0]));
    const names = generateIllusionNames(variables.size);
    let obfuscated = luaCode;
    let i = 0;
    for (const v of variables) {
        const regex = new RegExp(v + '\\b', 'g');
        obfuscated = obfuscated.replace(regex, names[i++]);
    }
    return obfuscated;
}

function buildCustomVM(luaSource) {
    let compileFn = compile;
    if (typeof compileFn !== 'function') {
        throw new Error("Compiler not provided or invalid.");
    }

    const proto = compileFn(luaSource);
    const serialized = serializeProto(proto);
    const { encryptedData, key, subMap } = encrypt(serialized);

    const subMapLua = "{" + subMap.join(',') + "}";
    const encryptedLua = "{" + encryptedData.join(',') + "}";

    const luaTemplate = `
local _V_bit = bit or bit32
if not _V_bit then error("Bit library required for execution") end
local _V_bxor = _V_bit.bxor
local _V_rshift = _V_bit.rshift
local _V_lshift = _V_bit.lshift
local _V_band = _V_bit.band
local _V_bor = _V_bit.bor
local _V_table_concat = table.concat
local _V_table_insert = table.insert
local _V_tonumber = tonumber
local _V_tostring = tostring
local _V_unpack = unpack or table.unpack
local _V_env = getfenv and getfenv() or _ENV

local _V_encData = ${encryptedLua}
local _V_key = ${key}
local _V_subMap = ${subMapLua}

local function _V_decrypt(_V_data, _V_k, _V_sm)
    local _V_res = {}
    local _V_len = #_V_data
    for _V_i = 1, _V_len do
        local _V_b = _V_data[_V_i]
        _V_b = _V_sm[_V_b + 1]
        _V_b = _V_bxor(_V_b, _V_band(_V_i, 255))
        _V_b = _V_bor(_V_band(_V_rshift(_V_b, 3), 255), _V_band(_V_lshift(_V_b, 5), 255))
        _V_b = _V_bxor(_V_b, _V_k)
        _V_res[_V_i] = _V_b
    end
    return _V_res
end

local _V_decData = _V_decrypt(_V_encData, _V_key, _V_subMap)

local function _V_deserialize(_V_bytes)
    local _V_pos = 1
    local function _V_readByte()
        local _V_b = _V_bytes[_V_pos]
        _V_pos = _V_pos + 1
        return _V_b
    end
    local function _V_readInt()
        local _V_b1 = _V_readByte()
        local _V_b2 = _V_readByte()
        local _V_b3 = _V_readByte()
        local _V_b4 = _V_readByte()
        return _V_bor(_V_lshift(_V_b4, 24), _V_lshift(_V_b3, 16), _V_lshift(_V_b2, 8), _V_b1)
    end
    local function _V_readString()
        local _V_len = _V_readInt()
        if _V_len == 0 then return "" end
        local _V_chars = {}
        for _V_i = 1, _V_len do
            _V_chars[_V_i] = string.char(_V_readByte())
        end
        return _V_table_concat(_V_chars)
    end
    local function _V_readProto()
        local _V_proto = {}
        _V_proto.numParams = _V_readByte()
        _V_proto.isVararg = _V_readByte() == 1
        _V_proto.maxStackSize = _V_readByte()
        local _V_codeLen = _V_readInt()
        _V_proto.code = {}
        for _V_i = 1, _V_codeLen do _V_proto.code[_V_i] = _V_readInt() end
        local _V_constLen = _V_readInt()
        _V_proto.constants = {}
        for _V_i = 1, _V_constLen do
            local _V_type = _V_readByte()
            if _V_type == 0 then _V_proto.constants[_V_i-1] = nil
            elseif _V_type == 1 then _V_proto.constants[_V_i-1] = _V_readByte() == 1
            elseif _V_type == 2 then _V_proto.constants[_V_i-1] = _V_tonumber(_V_readString())
            elseif _V_type == 3 then _V_proto.constants[_V_i-1] = _V_readString()
            end
        end
        local _V_protoLen = _V_readInt()
        _V_proto.prototypes = {}
        for _V_i = 1, _V_protoLen do _V_proto.prototypes[_V_i-1] = _V_readProto() end
        return _V_proto
    end
    return _V_readProto()
end

local _V_mainProto = _V_deserialize(_V_decData)

local function _V_wrap(_V_proto, _V_env, _V_upvals)
    return function(...)
        local _V_stack = {}
        local _V_varargs = {...}
        local _V_top = -1
        local _V_pc = 1
        local _V_code = _V_proto.code
        local _V_consts = _V_proto.constants
        local _V_protos = _V_proto.prototypes
        for _V_i = 1, _V_proto.numParams do _V_stack[_V_i-1] = _V_varargs[_V_i] end
        local _V_vararg_offset = _V_proto.numParams
        local function _V_RK(_V_x)
            if _V_x >= 256 then return _V_consts[_V_x - 256] else return _V_stack[_V_x] end
        end
        while true do
            local _V_op = _V_code[_V_pc]
            local _V_A = _V_code[_V_pc+1]
            local _V_B = _V_code[_V_pc+2]
            local _V_C = _V_code[_V_pc+3]
            _V_pc = _V_pc + 4
            
            -- CFF Simulation Wrapper
            local _V_state = _V_op
            if _V_state == 1 then _V_stack[_V_A] = _V_consts[_V_B]
            elseif _V_state == 2 then for _V_i = _V_A, _V_B do _V_stack[_V_i] = nil end
            elseif _V_state == 3 then _V_stack[_V_A] = _V_B ~= 0; if _V_C ~= 0 then _V_pc = _V_pc + 4 end
            elseif _V_state == 4 then _V_stack[_V_A] = _V_stack[_V_B]
            elseif _V_state == 5 then _V_stack[_V_A] = _V_env[_V_consts[_V_B]]
            elseif _V_state == 6 then _V_env[_V_consts[_V_B]] = _V_stack[_V_A]
            elseif _V_state == 7 then _V_stack[_V_A] = _V_stack[_V_B][_V_RK(_V_C)]
            elseif _V_state == 8 then _V_stack[_V_A][_V_RK(_V_B)] = _V_RK(_V_C)
            elseif _V_state == 9 then _V_stack[_V_A] = {}
            elseif _V_state == 10 then _V_stack[_V_A] = _V_RK(_V_B) + _V_RK(_V_C)
            elseif _V_state == 11 then _V_stack[_V_A] = _V_RK(_V_B) - _V_RK(_V_C)
            elseif _V_state == 12 then _V_stack[_V_A] = _V_RK(_V_B) * _V_RK(_V_C)
            elseif _V_state == 13 then _V_stack[_V_A] = _V_RK(_V_B) / _V_RK(_V_C)
            elseif _V_state == 14 then _V_stack[_V_A] = _V_RK(_V_B) % _V_RK(_V_C)
            elseif _V_state == 15 then _V_stack[_V_A] = _V_RK(_V_B) ^ _V_RK(_V_C)
            elseif _V_state == 16 then
                local _V_s = ""
                for _V_i = _V_B, _V_C do _V_s = _V_s .. _V_tostring(_V_stack[_V_i]) end
                _V_stack[_V_A] = _V_s
            elseif _V_state == 17 then _V_stack[_V_A] = -_V_stack[_V_B]
            elseif _V_state == 18 then _V_stack[_V_A] = not _V_stack[_V_B]
            elseif _V_state == 19 then _V_stack[_V_A] = #_V_stack[_V_B]
            elseif _V_state == 20 then if (_V_RK(_V_B) == _V_RK(_V_C)) ~= (_V_A ~= 0) then _V_pc = _V_pc + 4 end
            elseif _V_state == 21 then if (_V_RK(_V_B) < _V_RK(_V_C)) ~= (_V_A ~= 0) then _V_pc = _V_pc + 4 end
            elseif _V_state == 22 then if (_V_RK(_V_B) <= _V_RK(_V_C)) ~= (_V_A ~= 0) then _V_pc = _V_pc + 4 end
            elseif _V_state == 23 then _V_pc = _V_pc + _V_B * 4
            elseif _V_state == 24 then if (not _V_stack[_V_A]) ~= (_V_C ~= 0) then _V_pc = _V_pc + 4 end
            elseif _V_state == 25 then
                local _V_func = _V_stack[_V_A]
                local _V_args = {}
                local _V_limit = _V_B == 0 and _V_top or _V_A + _V_B - 1
                for _V_i = _V_A + 1, _V_limit do _V_table_insert(_V_args, _V_stack[_V_i]) end
                local _V_rets = {_V_func(_V_unpack(_V_args))}
                if _V_C == 0 then
                    _V_top = _V_A - 1
                    for _V_i = 1, #_V_rets do
                        _V_stack[_V_A + _V_i - 1] = _V_rets[_V_i]
                        _V_top = _V_top + 1
                    end
                else
                    for _V_i = 1, _V_C - 1 do _V_stack[_V_A + _V_i - 1] = _V_rets[_V_i] end
                end
            elseif _V_state == 26 then
                if _V_B == 0 then
                    local _V_rets = {}
                    for _V_i = _V_A, _V_top do _V_table_insert(_V_rets, _V_stack[_V_i]) end
                    return _V_unpack(_V_rets)
                else
                    local _V_rets = {}
                    for _V_i = _V_A, _V_A + _V_B - 2 do _V_table_insert(_V_rets, _V_stack[_V_i]) end
                    return _V_unpack(_V_rets)
                end
            elseif _V_state == 27 then
                _V_stack[_V_A] = _V_stack[_V_A] - _V_stack[_V_A+2]
                _V_pc = _V_pc + _V_B * 4
            elseif _V_state == 28 then
                local _V_step = _V_stack[_V_A+2]
                local _V_idx = _V_stack[_V_A] + _V_step
                _V_stack[_V_A] = _V_idx
                local _V_limit = _V_stack[_V_A+1]
                if (_V_step > 0 and _V_idx <= _V_limit) or (_V_step <= 0 and _V_idx >= _V_limit) then
                    _V_pc = _V_pc + _V_B * 4
                    _V_stack[_V_A+3] = _V_idx
                end
            elseif _V_state == 29 then
                local _V_func = _V_stack[_V_A]
                local _V_s = _V_stack[_V_A+1]
                local _V_var = _V_stack[_V_A+2]
                local _V_r1, _V_r2, _V_r3 = _V_func(_V_s, _V_var)
                if _V_r1 ~= nil then
                    _V_stack[_V_A+2] = _V_r1
                    _V_stack[_V_A+3] = _V_r1
                    _V_stack[_V_A+4] = _V_r2
                    _V_stack[_V_A+5] = _V_r3
                else
                    _V_pc = _V_pc + 4
                end
            elseif _V_state == 30 then
                local _V_fields = _V_B
                if _V_fields == 0 then _V_fields = _V_top - _V_A end
                local _V_offset = (_V_C - 1) * 50
                for _V_i = 1, _V_fields do _V_stack[_V_A][_V_offset + _V_i] = _V_stack[_V_A + _V_i] end
            elseif _V_state == 31 then
                local _V_p = _V_protos[_V_B]
                local _V_nups = _V_C
                local _V_newUpvals = {}
                for _V_i = 1, _V_nups do
                    local _V_upOp = _V_code[_V_pc]
                    local _V_upA = _V_code[_V_pc+1]
                    local _V_upB = _V_code[_V_pc+2]
                    local _V_upC = _V_code[_V_pc+3]
                    _V_pc = _V_pc + 4
                    if _V_upOp == 4 then
                        _V_newUpvals[_V_i] = {stack = _V_stack, idx = _V_upB}
                    elseif _V_upOp == 32 then
                        _V_newUpvals[_V_i] = _V_upvals[_V_upB]
                    end
                end
                _V_stack[_V_A] = _V_wrap(_V_p, _V_env, _V_newUpvals)
            elseif _V_state == 32 then
                local _V_uv = _V_upvals[_V_B]
                _V_stack[_V_A] = _V_uv.stack[_V_uv.idx]
            elseif _V_state == 33 then
                local _V_uv = _V_upvals[_V_B]
                _V_uv.stack[_V_uv.idx] = _V_stack[_V_A]
            elseif _V_state == 34 then
                _V_stack[_V_A+1] = _V_stack[_V_B]
                _V_stack[_V_A] = _V_stack[_V_B][_V_RK(_V_C)]
            elseif _V_state == 35 then
                local _V_wanted = _V_B - 1
                if _V_wanted < 0 then
                    _V_wanted = #_V_varargs - _V_vararg_offset
                    _V_top = _V_A + _V_wanted - 1
                end
                for _V_i = 1, _V_wanted do
                    _V_stack[_V_A + _V_i - 1] = _V_varargs[_V_vararg_offset + _V_i]
                end
            elseif _V_state == 36 then
                if (not _V_stack[_V_B]) ~= (_V_C ~= 0) then _V_stack[_V_A] = _V_stack[_V_B]
                else _V_pc = _V_pc + 4 end
            end
        end
    end
end

_V_wrap(_V_mainProto, _V_env, {})()
`;

    return obfuscateLua(luaTemplate);
}

if (require.main === module) {
    const args = process.argv.slice(2);
    if (args.length === 0) {
        console.error("Usage: node vm_builder.js <input.lua> [output.lua]");
        process.exit(1);
    }

    const inputFile = args[0];
    const outputFile = args[1] || 'obfuscated.lua';

    try {
        const luaSource = fs.readFileSync(inputFile, 'utf8');
        const obfuscated = buildCustomVM(luaSource);
        fs.writeFileSync(outputFile, obfuscated, 'utf8');
        console.log(`Successfully built VM to ${outputFile}`);
    } catch (err) {
        console.error("Error building VM:", err.message);
        console.error("Falling back to basic error logging.");
        process.exit(1);
    }
}

module.exports = { buildCustomVM };

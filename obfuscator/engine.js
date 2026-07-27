const luaparse = require('luaparse');
const crypto = require('crypto');

function generateRandomName() {
    const chars = ['I', 'l', '1'];
    let name = '_';
    for (let i = 0; i < 12; i++) {
        name += chars[Math.floor(Math.random() * chars.length)];
    }
    return name;
}

function obfuscateLua(code) {
    const ast = luaparse.parse(code, { locations: false });
    const stringMap = [];
    const xorKey = Math.floor(Math.random() * 200) + 20;

    // Process string literals
    function traverse(node) {
        if (!node || typeof node !== 'object') return;
        
        if (node.type === 'StringLiteral') {
            const rawVal = node.raw ? eval(node.raw) : node.value;
            const bytes = [];
            for (let i = 0; i < rawVal.length; i++) {
                bytes.push(rawVal.charCodeAt(i) ^ xorKey);
            }
            stringMap.push({
                original: rawVal,
                encryptedBytes: bytes
            });
        }

        for (const key in node) {
            if (node.hasOwnProperty(key) && key !== 'parent') {
                const child = node[key];
                if (Array.isArray(child)) {
                    child.forEach(traverse);
                } else if (typeof child === 'object') {
                    traverse(child);
                }
            }
        }
    }

    traverse(ast);

    // Simple AST to code representation generator with obfuscation
    // AST level obfuscation layer
    return {
        ast,
        stringMapCount: stringMap.length,
        xorKey
    };
}

module.exports = { obfuscateLua };

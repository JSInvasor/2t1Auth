const { Client, GatewayIntentBits, EmbedBuilder } = require('discord.js');
const express = require('express');
const rateLimit = require('express-rate-limit');
const crypto = require('crypto');
const fs = require('fs');

// ============================================
// AYARLAR - BUNLARI DEGISTIR
// ============================================
const CONFIG = {
    BOT_TOKEN: 'MTQ2MjU5MjU0MTQ5MTk4NjY0NA.GXpmup.To-RXcV9CpMYlknnz_FBgEkSlMKS_5MZnhyW-E',
    OWNER_ID: '853573968476504074',
    ADMIN_ROLE: 'Admin',
    PREFIX: '!',
    SITE_URL: 'https://2t1.online',
    API_PORT: 3001,
    RESET_REQUEST_CHANNEL: ''
};

// ============================================
// EXPRESS SERVER
// ============================================
const app = express();
app.use(express.json());

const limiter = rateLimit({
    windowMs: 60000,
    max: 60,
    message: 'error("Rate limited")'
});
app.use(limiter);

// ============================================
// DISCORD CLIENT
// ============================================
const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.MessageContent,
        GatewayIntentBits.GuildMembers
    ]
});

const path = require('path');

// ============================================
// DATABASE ISLEMLERI
// ============================================
const DB_PATH = path.resolve(__dirname, '../data/database.json');

let cachedDB = { users: {}, keys: {}, blacklist: [], resetRequests: [] };

function loadDB() {
    const dir = path.dirname(DB_PATH);
    if (!fs.existsSync(dir)) {
        try { fs.mkdirSync(dir, { recursive: true }); } catch (e) {}
    }

    if (!fs.existsSync(DB_PATH)) {
        saveDB(cachedDB);
        return cachedDB;
    }

    try {
        const content = fs.readFileSync(DB_PATH, 'utf8');
        if (content && content.trim().length > 0) {
            const data = JSON.parse(content);
            if (data && typeof data === 'object') {
                if (!data.keys || Array.isArray(data.keys)) data.keys = {};
                if (!data.users || Array.isArray(data.users)) data.users = {};
                if (!data.resetRequests || !Array.isArray(data.resetRequests)) data.resetRequests = [];
                cachedDB = data;
                return cachedDB;
            }
        }
    } catch (e) {
        console.error('loadDB read error (using cached memory DB):', e.message);
    }

    return cachedDB;
}

function saveDB(data) {
    cachedDB = data;
    const dir = path.dirname(DB_PATH);
    if (!fs.existsSync(dir)) {
        try { fs.mkdirSync(dir, { recursive: true }); } catch (e) {}
    }
    // DOĞRUDAN YAZ
    fs.writeFileSync(DB_PATH, JSON.stringify(data, null, 2));
}

// ============================================
// KEY OLUSTURMA
// ============================================
function generateKey() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let key = '2T1';
    for (let i = 0; i < 4; i++) {
        key += '-';
        for (let j = 0; j < 4; j++) {
            key += chars.charAt(Math.floor(Math.random() * chars.length));
        }
    }
    return key;
}

function generatePassword() {
    return crypto.randomBytes(6).toString('hex');
}

function generateRequestId() {
    return 'REQ-' + crypto.randomBytes(4).toString('hex').toUpperCase();
}

// ============================================
// BOT HAZIR
// ============================================
client.on('ready', () => {
    console.log('');
    console.log('================================');
    console.log('  2t1 Hub System Active');
    console.log('================================');
    console.log('  Bot: ' + client.user.tag);
    console.log('  API: Port ' + CONFIG.API_PORT);
    console.log('  Site: ' + CONFIG.SITE_URL);
    console.log('================================');
    console.log('');

    client.user.setActivity('2t1 Hub | !help', { type: 3 });
});

// ============================================
// KOMUTLAR
// ============================================
client.on('messageCreate', async (message) => {
    if (message.author.bot) return;
    if (!message.content.startsWith(CONFIG.PREFIX)) return;

    try {
        const args = message.content.slice(CONFIG.PREFIX.length).trim().split(/ +/);
        const command = args.shift().toLowerCase();
        const db = loadDB();

        // DEBUG
        if (command === 'debug') {
            if (message.author.id !== CONFIG.OWNER_ID) return;
            let info = '';
            info += `**DB_PATH:** \`${DB_PATH}\`\n`;
            info += `**__dirname:** \`${__dirname}\`\n`;
            info += `**Dosya var mı?:** \`${fs.existsSync(DB_PATH)}\`\n`;
            
            try {
                const stat = fs.statSync(DB_PATH);
                info += `**Dosya boyutu:** \`${stat.size} byte\`\n`;
                info += `**Son düzenleme:** \`${stat.mtime}\`\n`;
            } catch(e) {
                info += `**stat hatası:** \`${e.message}\`\n`;
            }

            let canRead = false;
            let canWrite = false;
            try { fs.accessSync(DB_PATH, fs.constants.R_OK); canRead = true; } catch(e) {}
            try { fs.accessSync(DB_PATH, fs.constants.W_OK); canWrite = true; } catch(e) {}
            info += `**Okuma izni:** \`${canRead}\`\n`;
            info += `**Yazma izni:** \`${canWrite}\`\n`;
            
            info += `**RAM cache key sayısı:** \`${Object.keys(cachedDB.keys).length}\`\n`;
            info += `**Bu anki db key sayısı:** \`${Object.keys(db.keys).length}\`\n`;
            
            try {
                const raw = fs.readFileSync(DB_PATH, 'utf8');
                const parsed = JSON.parse(raw);
                info += `**Dosyadaki GERÇEK key sayısı:** \`${Object.keys(parsed.keys || {}).length}\`\n`;
                const keyNames = Object.keys(parsed.keys || {}).slice(0, 3);
                info += `**İlk 3 key:** \`${keyNames.join(', ') || 'YOK'}\`\n`;
            } catch(e) {
                info += `**Dosya okuma hatası:** \`${e.message}\`\n`;
            }

            // Test yazma
            try {
                const testPath = DB_PATH + '.test';
                fs.writeFileSync(testPath, 'test');
                fs.unlinkSync(testPath);
                info += `**Yazma testi:** \`BAŞARILI\`\n`;
            } catch(e) {
                info += `**Yazma testi:** \`BAŞARISIZ - ${e.message}\`\n`;
            }

            return message.reply(info);
        }

        // USERDEBUG
        if (command === 'userdebug') {
            if (message.author.id !== CONFIG.OWNER_ID) return;
            try {
                const raw = fs.readFileSync(DB_PATH, 'utf8');
                const parsed = JSON.parse(raw);
                const userKeys = Object.keys(parsed.users || {});
                let info = `**Total Users on Disk:** ${userKeys.length}\n`;
                if (userKeys.length > 0) {
                    info += `**First 5 User IDs:**\n`;
                    info += userKeys.slice(0, 5).map(id => `- \`${id}\`: ${parsed.users[id].webUser}`).join('\n');
                }
                info += `\n**Your ID:** \`${message.author.id}\``;
                return message.reply(info);
            } catch(e) {
                return message.reply("Read Error: " + e.message);
            }
        }

        // HELP
    if (command === 'help') {
        const embed = new EmbedBuilder()
            .setTitle('2t1 Hub - Commands')
            .setColor('#6c5ce7')
            .addFields(
                {
                    name: 'User Commands',
                    value: '`!redeem <key>` - Use a license key\n`!status` - Check license status'
                },
                {
                    name: 'Admin Commands',
                    value: '`!genkey <days> <amount>` - Generate keys\n`!adduser <@user> <days>` - Add user\n`!deluser <@user>` - Delete user\n`!resetuser <@user>` - Reset HWID\n`!requests` - View reset requests\n`!approve <id>` - Approve request\n`!deny <id> [reason]` - Deny request\n`!users` - List users\n`!keys` - List keys'
                }
            )
            .setFooter({ text: '2t1 Hub Protection System' });

        return message.reply({ embeds: [embed] });
    }

    // REDEEM KEY
    if (command === 'redeem') {
        const rawKey = args[0];

        if (!rawKey) {
            return message.reply('Usage: `!redeem <key>`');
        }

        const cleanKey = rawKey.replace(/[`'"]/g, '').trim().toUpperCase();
        
        // NUCLEAR DOSYA OKUMA
        let diskData = { users: {}, keys: {}, blacklist: [], resetRequests: [] };
        try {
            const rawFile = fs.readFileSync(DB_PATH, 'utf8');
            diskData = JSON.parse(rawFile);
            if (!diskData.keys || Array.isArray(diskData.keys)) diskData.keys = {};
            if (!diskData.users || Array.isArray(diskData.users)) diskData.users = {};
        } catch (e) {
            return message.reply('Database okunamadı! Hata: ' + e.message);
        }

        const availableKeys = Object.keys(diskData.keys);
        const keyUpper = availableKeys.find(k => k.trim().toUpperCase() === cleanKey);

        if (!keyUpper || !diskData.keys[keyUpper]) {
            return message.reply(`Invalid key! (Aranan: \`${cleanKey}\` - Veritabanındaki toplam key sayısı: ${availableKeys.length})`);
        }

        if (diskData.keys[keyUpper].used) {
            return message.reply('This key has already been used!');
        }

        const odaRobloxUserId = String(message.author.id);
        const days = diskData.keys[keyUpper].days;
        const now = Date.now();
        const expiry = now + (days * 24 * 60 * 60 * 1000);

        let webUser = message.author.username.toLowerCase().replace(/[^a-z0-9]/g, '');
        if (webUser.length < 3) webUser = 'user' + odaRobloxUserId.slice(-6);

        const exists = Object.values(diskData.users).some(u => u.webUser === webUser);
        if (exists) webUser = webUser + odaRobloxUserId.slice(-4);

        const webPass = generatePassword();

        if (diskData.users[odaRobloxUserId]) {
            if (diskData.users[odaRobloxUserId].expiry > now) {
                diskData.users[odaRobloxUserId].expiry += days * 24 * 60 * 60 * 1000;
            } else {
                diskData.users[odaRobloxUserId].expiry = expiry;
                diskData.users[odaRobloxUserId].hwid = null;
            }
        } else {
            diskData.users[odaRobloxUserId] = {
                discordTag: message.author.tag,
                discordId: odaRobloxUserId,
                webUser: webUser,
                webPass: webPass,
                hwid: null,
                robloxName: null,
                robloxId: null,
                expiry: expiry,
                createdAt: now,
                logins: 0
            };
        }

        diskData.keys[keyUpper].used = true;
        diskData.keys[keyUpper].usedBy = odaRobloxUserId;
        diskData.keys[keyUpper].usedAt = now;

        // NUCLEAR DOSYAYA YAZMA
        let writeOK = false;
        let writeByte = 0;
        try {
            const jsonToWrite = JSON.stringify(diskData, null, 2);
            writeByte = jsonToWrite.length;
            fs.writeFileSync(DB_PATH, jsonToWrite);
            writeOK = true;
        } catch (e) {
            return message.reply('CRITICAL ERROR: KULLANICI DOSYAYA YAZILAMADI! ' + e.message);
        }

        // DOĞRULAMA (Yazdıktan hemen sonra geri oku)
        let verifyUserExists = false;
        try {
            const checkData = JSON.parse(fs.readFileSync(DB_PATH, 'utf8'));
            if (checkData.users && checkData.users[odaRobloxUserId]) {
                verifyUserExists = true;
            }
        } catch(e) {}

        // GLOBAL CACHE GÜNCELLE
        cachedDB = diskData;

        // DİAGNOSTİK MESAJ (Kurucu için)
        if (message.author.id === CONFIG.OWNER_ID) {
            const diagMsg = `**NUCLEAR REDEEM INFO:**\nWrite OK: \`${writeOK}\`\nBytes Written: \`${writeByte}\`\nUser Exists In File Now: \`${verifyUserExists}\``;
            message.reply(diagMsg);
        }

        const embed = new EmbedBuilder()
            .setTitle('Key Redeemed!')
            .setColor('#50ff88')
            .addFields(
                { name: 'Duration', value: days + ' days', inline: true },
                { name: 'Expires', value: new Date(diskData.users[odaRobloxUserId].expiry).toLocaleDateString(), inline: true }
            )
            .setFooter({ text: 'Check your DMs for login info!' });

        message.reply({ embeds: [embed] });

        try {
            const dmEmbed = new EmbedBuilder()
                .setTitle('2t1 Hub - Login Credentials')
                .setColor('#6c5ce7')
                .setDescription('Login at: **' + CONFIG.SITE_URL + '**')
                .addFields(
                    { name: 'Username', value: '`' + diskData.users[odaRobloxUserId].webUser + '`', inline: true },
                    { name: 'Password', value: '`' + diskData.users[odaRobloxUserId].webPass + '`', inline: true },
                    { name: 'Expiration', value: new Date(diskData.users[odaRobloxUserId].expiry).toLocaleString(), inline: false }
                )
                .setFooter({ text: 'Keep this information safe!' });
            
            await message.author.send({ embeds: [dmEmbed] });
        } catch (error) {
            message.channel.send('Could not send DM. Enable your DMs!');
        }

        try {
            const role = message.guild.roles.cache.find(r => r.name === 'Customer');
            if (role) await message.member.roles.add(role);
        } catch (e) {}
    }

    // STATUS
    if (command === 'status') {
        let diskUsers = {};
        try {
            const rawFile = fs.readFileSync(DB_PATH, 'utf8');
            const diskData = JSON.parse(rawFile);
            diskUsers = diskData.users || {};
        } catch (e) {}

        const user = diskUsers[String(message.author.id)];

        if (!user) {
            return message.reply('You are not registered! Use `!redeem <key>` first.');
        }

        const now = Date.now();
        const isActive = user.expiry > now;
        const daysLeft = Math.ceil((user.expiry - now) / (24 * 60 * 60 * 1000));

        const embed = new EmbedBuilder()
            .setTitle('License Status')
            .setColor(isActive ? '#50ff88' : '#ff5050')
            .addFields(
                { name: 'Status', value: isActive ? 'ACTIVE' : 'EXPIRED', inline: true },
                { name: 'Days Left', value: isActive ? daysLeft + ' days' : 'N/A', inline: true },
                { name: 'HWID', value: user.hwid ? 'Locked' : 'Not locked', inline: true },
                { name: 'Roblox', value: user.robloxName || 'Not linked', inline: true }
            );

        message.reply({ embeds: [embed] });
    }

    // ============================================
    // ADMIN KOMUTLARI
    // ============================================
    const isAdminCommand = ['genkey', 'adduser', 'deluser', 'resetuser', 'requests', 'approve', 'deny', 'users', 'keys'].includes(command);

    if (isAdminCommand) {
        const isOwner = message.author.id === CONFIG.OWNER_ID;
        let hasAdminRole = false;
        
        if (message.member && message.member.roles) {
            hasAdminRole = message.member.roles.cache.some(r => r.name.toLowerCase() === CONFIG.ADMIN_ROLE.toLowerCase());
        }

        if (!isOwner && !hasAdminRole) {
            return message.reply(`⛔ **Yetkin Yok!**\nSenin ID'n: \`${message.author.id}\`\nKurucu ID'si: \`${CONFIG.OWNER_ID}\`\nAdmin rolün var mı?: \`${hasAdminRole}\``);
        }
    }

    // GENKEY
    if (command === 'genkey') {
        const days = parseInt(args[0]) || 30;
        const amount = Math.min(parseInt(args[1]) || 1, 50);

        // Dosyayi oku
        let existing = { users: {}, keys: {}, blacklist: [], resetRequests: [] };
        try {
            const rawFile = fs.readFileSync(DB_PATH, 'utf8');
            existing = JSON.parse(rawFile);
            if (!existing.keys || Array.isArray(existing.keys)) existing.keys = {};
            if (!existing.users || Array.isArray(existing.users)) existing.users = {};
        } catch (e) {}

        const freshObj = {
            users: {},
            keys: {},
            blacklist: existing.blacklist || [],
            resetRequests: existing.resetRequests || []
        };

        // Eski verileri koru
        if (existing.users && typeof existing.users === 'object') {
            const oldUsers = Object.keys(existing.users);
            for (let i = 0; i < oldUsers.length; i++) {
                freshObj.users[oldUsers[i]] = existing.users[oldUsers[i]];
            }
        }
        if (existing.keys && typeof existing.keys === 'object') {
            const oldKeys = Object.keys(existing.keys);
            for (let i = 0; i < oldKeys.length; i++) {
                freshObj.keys[oldKeys[i]] = existing.keys[oldKeys[i]];
            }
        }

        // Key'leri olustur
        const generatedKeys = [];
        for (let i = 0; i < amount; i++) {
            const newKey = generateKey();
            freshObj.keys[newKey] = {
                days: days,
                used: false,
                createdBy: String(message.author.id),
                createdAt: Date.now()
            };
            generatedKeys.push(newKey);
        }

        // Dosyaya kaydet
        try {
            fs.writeFileSync(DB_PATH, JSON.stringify(freshObj, null, 2));
            cachedDB = freshObj;
        } catch (e) {
            return message.reply('Veritabanına yazılamadı: ' + e.message);
        }

        const embed = new EmbedBuilder()
            .setTitle('License Keys Generated')
            .setColor('#6c5ce7')
            .setDescription('```\n' + generatedKeys.join('\n') + '\n```')
            .addFields(
                { name: 'Duration', value: days + ' Days', inline: true },
                { name: 'Amount', value: String(amount), inline: true }
            )
            .setFooter({ text: '2t1 Protection System' });

        try {
            await message.author.send({ embeds: [embed] });
            message.reply('Keys sent to your DMs!');
        } catch (e) {
            message.reply({ content: 'DMs closed! Here are your keys:', embeds: [embed] });
        }
    }

    // ADDUSER
    if (command === 'adduser') {
        const target = message.mentions.users.first();
        const days = parseInt(args[1]) || 30;

        if (!target) {
            return message.reply('Usage: `!adduser @user <days>`');
        }

        const now = Date.now();
        const expiry = now + (days * 24 * 60 * 60 * 1000);

        let webUser = target.username.toLowerCase().replace(/[^a-z0-9]/g, '');
        if (webUser.length < 3) webUser = 'user' + target.id.slice(-6);

        const webPass = generatePassword();

        db.users[target.id] = {
            discordTag: target.tag,
            discordId: target.id,
            webUser: webUser,
            webPass: webPass,
            hwid: null,
            robloxName: null,
            robloxId: null,
            expiry: expiry,
            createdAt: now,
            logins: 0
        };

        saveDB(db);

        message.reply('Added **' + target.tag + '** with ' + days + ' days.\nUsername: `' + webUser + '`\nPassword: `' + webPass + '`');
    }

    // DELUSER
    if (command === 'deluser') {
        const target = message.mentions.users.first();

        if (!target) {
            return message.reply('Usage: `!deluser @user`');
        }

        if (!db.users[target.id]) {
            return message.reply('User not found!');
        }

        delete db.users[target.id];
        saveDB(db);

        message.reply('Deleted **' + target.tag + '** from whitelist.');
    }

    // RESETUSER
    if (command === 'resetuser') {
        const target = message.mentions.users.first();

        if (!target) {
            return message.reply('Usage: `!resetuser @user`');
        }

        if (!db.users[target.id]) {
            return message.reply('User not found!');
        }

        db.users[target.id].hwid = null;
        saveDB(db);

        message.reply('HWID reset for **' + target.tag + '**');
    }

    // REQUESTS
    if (command === 'requests') {
        if (!db.resetRequests || db.resetRequests.length === 0) {
            return message.reply('No pending HWID reset requests.');
        }

        const pending = db.resetRequests.filter(r => r.status === 'pending');

        if (pending.length === 0) {
            return message.reply('No pending HWID reset requests.');
        }

        let list = '';
        pending.slice(0, 10).forEach(req => {
            const user = db.users[req.odaRobloxUserId];
            const date = new Date(req.createdAt).toLocaleDateString();
            list += '`' + req.id + '` - ' + (user?.discordTag || 'Unknown') + ' - ' + date + '\n';
        });

        const embed = new EmbedBuilder()
            .setTitle('Pending Requests (' + pending.length + ')')
            .setColor('#ffaa50')
            .setDescription(list)
            .setFooter({ text: '!approve <id> or !deny <id> [reason]' });

        message.reply({ embeds: [embed] });
    }

    // APPROVE
    if (command === 'approve') {
        const requestId = args[0]?.toUpperCase();

        if (!requestId) {
            return message.reply('Usage: `!approve <request-id>`');
        }

        if (!db.resetRequests) db.resetRequests = [];

        const reqIndex = db.resetRequests.findIndex(r => r.id === requestId);

        if (reqIndex === -1) {
            return message.reply('Request not found!');
        }

        const req = db.resetRequests[reqIndex];

        if (req.status !== 'pending') {
            return message.reply('This request has already been ' + req.status + '!');
        }

        if (db.users[req.odaRobloxUserId]) {
            db.users[req.odaRobloxUserId].hwid = null;
        }

        db.resetRequests[reqIndex].status = 'approved';
        db.resetRequests[reqIndex].reviewedBy = message.author.id;
        db.resetRequests[reqIndex].reviewedAt = Date.now();

        saveDB(db);

        try {
            const user = await client.users.fetch(req.odaRobloxUserId);
            const dmEmbed = new EmbedBuilder()
                .setTitle('HWID Reset Approved')
                .setColor('#50ff88')
                .setDescription('Your HWID reset request has been approved!');

            await user.send({ embeds: [dmEmbed] });
        } catch (e) {}

        message.reply('Request `' + requestId + '` approved!');
    }

    // DENY
    if (command === 'deny') {
        const requestId = args[0]?.toUpperCase();
        const reason = args.slice(1).join(' ') || 'No reason provided';

        if (!requestId) {
            return message.reply('Usage: `!deny <request-id> [reason]`');
        }

        if (!db.resetRequests) db.resetRequests = [];

        const reqIndex = db.resetRequests.findIndex(r => r.id === requestId);

        if (reqIndex === -1) {
            return message.reply('Request not found!');
        }

        const req = db.resetRequests[reqIndex];

        if (req.status !== 'pending') {
            return message.reply('This request has already been ' + req.status + '!');
        }

        db.resetRequests[reqIndex].status = 'denied';
        db.resetRequests[reqIndex].reviewedBy = message.author.id;
        db.resetRequests[reqIndex].reviewedAt = Date.now();
        db.resetRequests[reqIndex].denyReason = reason;

        saveDB(db);

        try {
            const user = await client.users.fetch(req.odaRobloxUserId);
            const dmEmbed = new EmbedBuilder()
                .setTitle('HWID Reset Denied')
                .setColor('#ff5050')
                .setDescription('Reason: ' + reason);

            await user.send({ embeds: [dmEmbed] });
        } catch (e) {}

        message.reply('Request `' + requestId + '` denied.');
    }

    // USERS
    if (command === 'users') {
        const users = Object.entries(db.users);

        if (users.length === 0) {
            return message.reply('No users.');
        }

        const now = Date.now();
        let list = '';

        users.slice(0, 20).forEach(([id, data]) => {
            const status = data.expiry > now ? '[ON]' : '[OFF]';
            list += status + ' ' + (data.discordTag || id) + '\n';
        });

        const embed = new EmbedBuilder()
            .setTitle('Users (' + users.length + ')')
            .setColor('#6c5ce7')
            .setDescription('```\n' + list + '```');

        message.reply({ embeds: [embed] });
    }

    // KEYS
    if (command === 'keys') {
        const unused = Object.entries(db.keys).filter(([_, d]) => !d.used);

        if (unused.length === 0) {
            return message.reply('No unused keys.');
        }

        let list = '';
        unused.slice(0, 20).forEach(([key, data]) => {
            list += key + ' - ' + data.days + 'd\n';
        });

        const embed = new EmbedBuilder()
            .setTitle('Unused Keys (' + unused.length + ')')
            .setColor('#6c5ce7')
            .setDescription('```\n' + list + '```');

        try {
            await message.author.send({ embeds: [embed] });
            message.reply('Keys sent to DMs!');
        } catch (e) {
            message.reply({ embeds: [embed] });
        }
    }
    } catch (err) {
        console.error(err);
        message.reply(`⛔ **Bota Hata Oluştu!** \n\`\`\`\n${err.message}\n\`\`\``);
    }
});

// ============================================
// RAW SCRIPT API REMOVED - All scripts are now served via Custom VM through api.php

// API - HWID RESET TALEBI
app.post('/api/reset-request', (req, res) => {
    const { discordId, reason } = req.body;

    if (!discordId || !reason) {
        return res.status(400).json({ success: false, error: 'Missing fields' });
    }

    const db = loadDB();

    if (!db.users[discordId]) {
        return res.status(404).json({ success: false, error: 'User not found' });
    }

    if (!db.users[discordId].hwid) {
        return res.status(400).json({ success: false, error: 'HWID not locked yet' });
    }

    if (!db.resetRequests) db.resetRequests = [];

    const pendingExists = db.resetRequests.some(
        r => r.odaRobloxUserId === discordId && r.status === 'pending'
    );

    if (pendingExists) {
        return res.status(400).json({ success: false, error: 'You already have a pending request' });
    }

    const requestId = generateRequestId();

    db.resetRequests.push({
        id: requestId,
        odaRobloxUserId: discordId,
        reason: reason,
        status: 'pending',
        createdAt: Date.now()
    });

    saveDB(db);

    res.json({ success: true, requestId: requestId });
});

// API - TALEP DURUMU
app.get('/api/reset-status/:odaRobloxUserId', (req, res) => {
    const odaRobloxUserId = req.params.odaRobloxUserId;

    const db = loadDB();

    if (!db.resetRequests) {
        return res.json({ requests: [] });
    }

    const userRequests = db.resetRequests
        .filter(r => r.odaRobloxUserId === odaRobloxUserId)
        .slice(-5)
        .map(r => ({
            id: r.id,
            status: r.status,
            reason: r.reason,
            createdAt: r.createdAt,
            denyReason: r.denyReason || null
        }));

    res.json({ requests: userRequests });
});

app.get('/health', (req, res) => {
    res.json({ status: 'ok' });
});

app.use((req, res) => {
    res.status(404).send('error("Not found")');
});

// ============================================
// BASLA
// ============================================
app.listen(CONFIG.API_PORT, '0.0.0.0', () => {
    console.log('[+] API running on port ' + CONFIG.API_PORT);
});

client.login(CONFIG.BOT_TOKEN);
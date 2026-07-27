<?php
header('Content-Type: text/plain');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$blocked = ['Mozilla','Chrome','Safari','Edge','Firefox','Opera','Trident','Gecko','WebKit','curl','wget','PostmanRuntime','HTTPie','insomnia','Fiddler','python-requests','Java','Go-http-client','axios','node-fetch'];
foreach ($blocked as $b) {
    if (stripos($ua, $b) !== false) {
        die('return function(_)local _=_or""end');
    }
}
if (strlen($ua) > 200) die('return function()end');

// ── Polymorphic variable name generator ──
function rvar() {
    $chars = ['I','l','1'];
    $n = '_';
    for ($i = 0; $i < 12; $i++) $n .= $chars[array_rand($chars)];
    return $n;
}

$v = [];
$used = [];
for ($i = 0; $i < 25; $i++) {
    do { $name = rvar(); } while (in_array($name, $used));
    $v[$i] = $name;
    $used[] = $name;
}

// Junk values for dead code
$j1 = random_int(1000, 99999);
$j2 = random_int(100, 9999);
$j3 = random_int(1000, 99999);

echo <<<LUA
return function({$v[0]})
    {$v[0]} = {$v[0]} or _G.Key or _G.key or _G.script_key or (getgenv and getgenv().Key) or (getgenv and getgenv().key) or (getgenv and getgenv().script_key)
    if not {$v[0]} or type({$v[0]}) ~= "string" or #({$v[0]}) < 10 then return end

    local {$v[1]} = {}
    local _cf = clonefunction
    {$v[1]}.pc = _cf(pcall)
    {$v[1]}.ty = _cf(type)
    {$v[1]}.ts = _cf(tostring)
    {$v[1]}.tn = _cf(tonumber)
    {$v[1]}.sl = _cf(string.len)
    {$v[1]}.sf = _cf(string.format)
    {$v[1]}.ss = _cf(string.sub)
    {$v[1]}.sb = _cf(string.byte)
    {$v[1]}.sc = _cf(string.char)
    {$v[1]}.sg = _cf(string.gsub)
    {$v[1]}.p  = _cf(print)
    {$v[1]}.w  = _cf(warn)
    {$v[1]}.hg = _cf(game.HttpGet)
    {$v[1]}.tc = _cf(table.concat)
    {$v[1]}.mr = _cf(math.random)

    local {$v[20]} = {$j1}; {$v[20]} = {$v[20]} * {$j2} + {$j3}; {$v[20]} = nil

    local {$v[2]} = getgenv()
    local H = game:GetService("HttpService")
    local P = game:GetService("Players").LocalPlayer

    local {$v[3]}
    {$v[1]}.pc(function()
        {$v[3]} = game:GetService("RbxAnalyticsService"):GetClientId()
    end)
    if not {$v[3]} or {$v[3]} == "" then
        {$v[1]}.pc(function()
            if gethwid then {$v[3]} = gethwid() end
        end)
    end
    if not {$v[3]} or {$v[3]} == "" then
        {$v[1]}.pc(function()
            if getfingerprint then {$v[3]} = getfingerprint() end
        end)
    end
    if not {$v[3]} or {$v[3]} == "" then
        {$v[3]} = {$v[1]}.ts(P.UserId)
    end

    local {$v[4]} = true
    {$v[1]}.pc(function()
        local _ls = loadstring or load
        if _ls then
            if islclosure and islclosure(_ls) then
                {$v[4]} = false
            end
            if iscclosure and not iscclosure(_ls) then
                {$v[4]} = false
            end
        end
    end)

    {$v[1]}.pc(function()
        if iscclosure and not iscclosure(game.HttpGet) then
            {$v[4]} = false
        end
    end)

    if not {$v[4]} then return end

    local function {$v[5]}(reason)
        {$v[1]}.pc(function()
            {$v[1]}.hg(game, "https://2t1.online/api/v2/report.php?type=" .. H:UrlEncode(reason) .. "&hwid=" .. H:UrlEncode({$v[3]}) .. "&user=" .. H:UrlEncode(P.Name))
        end)
    end

    local {$v[11]} = {$v[1]}.ts({$v[1]}.mr(100000, 999999)) .. {$v[1]}.ts(os.time())
    local {$v[12]} = "https://2t1.online/api.php?key=" .. H:UrlEncode({$v[0]}) .. "&hwid=" .. H:UrlEncode({$v[3]}) .. "&rid=" .. P.UserId .. "&rname=" .. H:UrlEncode(P.Name) .. "&nonce=" .. {$v[11]}

    local _ok, _res = {$v[1]}.pc(function()
        return {$v[1]}.hg(game, {$v[12]})
    end)

    if not _ok or not _res then return end
    if {$v[1]}.ty(_res) ~= "string" then return end
    if _res:find("error") then return end

    local {$v[13]} = loadstring or load
    if not {$v[13]} then return end

    local {$v[14]}, {$v[15]} = {$v[1]}.pc({$v[13]}, _res)
    _res = nil

    if {$v[14]} and {$v[15]} then
        local {$v[16]}, {$v[17]} = {$v[1]}.pc({$v[15]})
        {$v[15]} = nil
    end

    {$v[1]}.pc(function()
        collectgarbage("collect")
        collectgarbage("collect")
    end)
end
LUA;



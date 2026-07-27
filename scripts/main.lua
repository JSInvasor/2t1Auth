--[[
	2t1 STUDIO LOADER  v5
	Lightning shatter intro

	Sequence
	  0  charge     a ring pulses out, the screen starts to tremble
	  1  strike     bolts tear across the screen, hard flash, hard shake
	  2  fracture   cracks crawl outward from the impact and branch
	  3  shatter    everything flares, the glass gives way, shards fall
	  4  settle     sparks drift, the logo resolves out of the debris

	Performance
	  Object counts are kept low on purpose. The bolt reveal advances in
	  chunks per frame instead of yielding per segment, and each bolt fades
	  as a single group rather than one tween per segment.
	  Turn FX.Quality down if you are on a weak device.
]]

local TweenService = game:GetService("TweenService")
local RunService   = game:GetService("RunService")
local Players      = game:GetService("Players")
local LocalPlayer  = Players.LocalPlayer
local Camera       = workspace.CurrentCamera

local LOGO_ID = "rbxassetid://122687530154939"

-- ══════════════════════════════════════
--  TUNING
--  Quality 1 = light, 2 = normal, 3 = heavy
-- ══════════════════════════════════════
local QUALITY = 2

local PRESETS = {
	[1] = { bolts = 3, depth = 3, branches = 1, cracks = 4, crackDepth = 3,
	        crackSub = 0, shards = 12, sparks = 16, chromatic = false },
	[2] = { bolts = 5, depth = 4, branches = 2, cracks = 6, crackDepth = 4,
	        crackSub = 1, shards = 20, sparks = 28, chromatic = true },
	[3] = { bolts = 7, depth = 5, branches = 3, cracks = 9, crackDepth = 5,
	        crackSub = 2, shards = 34, sparks = 44, chromatic = true },
}

local FX = PRESETS[QUALITY]
FX.jitter   = 240      -- how far a bolt wanders off a straight line
FX.shakeMax = 20       -- pixels at peak

local WHITE   = Color3.fromRGB(255, 255, 255)
local ICEBLUE = Color3.fromRGB(196, 226, 255)
local WARM    = Color3.fromRGB(255, 238, 216)
local GREEN   = Color3.fromRGB(70, 220, 130)
local RED     = Color3.fromRGB(230, 60, 80)
local SOFT    = Color3.fromRGB(200, 210, 230)

-- ══════════════════════════════════════
--  BOOT
-- ══════════════════════════════════════
local parentGui = (function()
	local ok, res = pcall(function() if gethui then return gethui() end end)
	if ok and res then return res end
	local ok2, res2 = pcall(function() return game:GetService("CoreGui") end)
	if ok2 and res2 then return res2 end
	return LocalPlayer:WaitForChild("PlayerGui")
end)()

local old = parentGui:FindFirstChild("2t1Loader")
if old then old:Destroy() end

local ScreenGui = Instance.new("ScreenGui")
ScreenGui.Name = "2t1Loader"
ScreenGui.ResetOnSpawn = false
ScreenGui.ZIndexBehavior = Enum.ZIndexBehavior.Sibling
ScreenGui.IgnoreGuiInset = true
ScreenGui.DisplayOrder = 9999
ScreenGui.Parent = parentGui

local Stage = Instance.new("Frame")
Stage.Name = "Stage"
Stage.Size = UDim2.new(1, 0, 1, 0)
Stage.BackgroundTransparency = 1
Stage.BorderSizePixel = 0
Stage.ZIndex = 100
Stage.Parent = ScreenGui

local function Tween(obj, dur, props, style, dir)
	local t = TweenService:Create(
		obj,
		TweenInfo.new(dur, style or Enum.EasingStyle.Quint, dir or Enum.EasingDirection.Out),
		props
	)
	t:Play()
	return t
end

local function viewport()
	local vp = Camera.ViewportSize
	return vp.X, vp.Y
end

-- CanvasGroup lets a whole bolt fade with one tween instead of one per
-- segment. If the client does not have it, fall back to a plain Frame.
local hasCanvasGroup = pcall(function()
	local c = Instance.new("CanvasGroup")
	c:Destroy()
end)

local function newGroup(z)
	local g
	if hasCanvasGroup then
		g = Instance.new("CanvasGroup")
		g.GroupTransparency = 0
	else
		g = Instance.new("Frame")
	end
	g.Size = UDim2.new(1, 0, 1, 0)
	g.BackgroundTransparency = 1
	g.BorderSizePixel = 0
	g.ZIndex = z or 120
	g.Parent = Stage
	return g
end

local function fadeGroup(g, dur, after)
	if hasCanvasGroup then
		Tween(g, dur, { GroupTransparency = 1 }, Enum.EasingStyle.Quad)
	else
		for _, c in ipairs(g:GetChildren()) do
			if c:IsA("Frame") then
				Tween(c, dur, { BackgroundTransparency = 1 }, Enum.EasingStyle.Quad)
			end
		end
	end
	task.delay(dur + 0.1, function()
		if g and g.Parent then g:Destroy() end
		if after then after() end
	end)
end

-- ══════════════════════════════════════
--  PRIMITIVES
-- ══════════════════════════════════════
local function segment(parent, a, b, thickness, color, z, transparency)
	local delta = b - a
	local len = delta.Magnitude
	if len < 0.5 then return nil end

	local f = Instance.new("Frame")
	f.AnchorPoint = Vector2.new(0, 0.5)
	f.Position = UDim2.fromOffset(a.X, a.Y)
	f.Size = UDim2.fromOffset(len, thickness)
	f.Rotation = math.deg(math.atan2(delta.Y, delta.X))
	f.BackgroundColor3 = color
	f.BackgroundTransparency = transparency or 0
	f.BorderSizePixel = 0
	f.ZIndex = z or 110
	f.Parent = parent

	local c = Instance.new("UICorner")
	c.CornerRadius = UDim.new(1, 0)
	c.Parent = f

	return f
end

-- round halo, built from circles so nothing ever reads as a box
local function halo(parent, pos, size, color, transparency, z)
	local g = Instance.new("Frame")
	g.AnchorPoint = Vector2.new(0.5, 0.5)
	g.Position = UDim2.fromOffset(pos.X, pos.Y)
	g.Size = UDim2.fromOffset(size, size)
	g.BackgroundColor3 = color
	g.BackgroundTransparency = transparency
	g.BorderSizePixel = 0
	g.ZIndex = z or 105
	g.Parent = parent

	local c = Instance.new("UICorner")
	c.CornerRadius = UDim.new(1, 0)
	c.Parent = g

	-- soften the edge so it fades outward instead of ending hard
	local grad = Instance.new("UIGradient")
	grad.Transparency = NumberSequence.new({
		NumberSequenceKeypoint.new(0, 1),
		NumberSequenceKeypoint.new(0.35, 0.55),
		NumberSequenceKeypoint.new(0.5, 0),
		NumberSequenceKeypoint.new(0.65, 0.55),
		NumberSequenceKeypoint.new(1, 1),
	})
	grad.Parent = g

	return g
end

-- midpoint displacement, the reason real lightning looks like lightning
local function fracture(a, b, depth, jitter, out)
	if depth <= 0 then
		out[#out + 1] = b
		return
	end
	local mid = (a + b) * 0.5
	local dir = b - a
	local perp = Vector2.new(-dir.Y, dir.X)
	if perp.Magnitude > 0.001 then
		perp = perp.Unit
		mid = mid + perp * ((math.random() - 0.5) * jitter)
	end
	fracture(a, mid, depth - 1, jitter * 0.55, out)
	fracture(mid, b, depth - 1, jitter * 0.55, out)
end

-- reveal a list of frames over `dur` seconds without yielding per item
local function chunkedReveal(list, dur)
	local n = #list
	if n == 0 then return end
	local frames = math.max(1, math.floor(dur * 60))
	local perFrame = math.ceil(n / frames)

	local i = 1
	local conn
	conn = RunService.RenderStepped:Connect(function()
		for _ = 1, perFrame do
			local f = list[i]
			if not f then
				conn:Disconnect()
				return
			end
			if f.Parent then f.Visible = true end
			i = i + 1
		end
		if i > n then conn:Disconnect() end
	end)
end

-- ══════════════════════════════════════
--  LIGHTNING
-- ══════════════════════════════════════
local function bolt(origin, target, opts)
	opts = opts or {}
	local depth    = opts.depth or FX.depth
	local jitter   = opts.jitter or FX.jitter
	local core     = opts.core or 2.4
	local life     = opts.life or 0.4
	local reveal   = opts.reveal or 0.06
	local z        = opts.z or 130
	local branches = opts.branches or 0
	local colour   = opts.color or WHITE

	local pts = { origin }
	fracture(origin, target, depth, jitter, pts)

	local group = newGroup(z)
	local pieces = {}

	for i = 1, #pts - 1 do
		local a, b = pts[i], pts[i + 1]

		if FX.chromatic then
			local g1 = segment(group, a + Vector2.new(-1.5, 0), b + Vector2.new(-1.5, 0),
				core * 1.7, ICEBLUE, 1, 0.55)
			if g1 then pieces[#pieces + 1] = g1 end
		end

		local s = segment(group, a, b, core, colour, 3, 0)
		if s then pieces[#pieces + 1] = s end
	end

	for _, f in ipairs(pieces) do f.Visible = false end
	chunkedReveal(pieces, reveal)

	if branches > 0 then
		for _ = 1, branches do
			local lo = math.max(2, math.floor(#pts * 0.3))
			local hi = math.max(lo, math.floor(#pts * 0.8))
			local from = pts[math.random(lo, hi)]
			local dir = target - origin
			dir = dir.Magnitude > 0.001 and dir.Unit or Vector2.new(1, 0)
			local ang = math.rad(math.random(-70, 70))
			local rot = Vector2.new(
				dir.X * math.cos(ang) - dir.Y * math.sin(ang),
				dir.X * math.sin(ang) + dir.Y * math.cos(ang))
			local len = (target - origin).Magnitude * (math.random(20, 45) / 100)

			bolt(from, from + rot * len, {
				depth = math.max(depth - 1, 2),
				jitter = jitter * 0.5,
				core = core * 0.55,
				life = life * 0.75,
				reveal = reveal * 0.6,
				z = z - 1,
				branches = 0,
				color = colour,
			})
		end
	end

	-- flicker once, then fade the whole group in a single tween
	task.spawn(function()
		task.wait(reveal + 0.02)
		if hasCanvasGroup and group.Parent then
			group.GroupTransparency = 0.7
			task.wait(0.03)
			if group.Parent then group.GroupTransparency = 0 end
		end
		task.wait(0.04)
		if group.Parent then fadeGroup(group, life) end
	end)

	return pts
end

-- ══════════════════════════════════════
--  CRACK
-- ══════════════════════════════════════
local crackGroups = {}

local function crack(origin, angle, length, opts)
	opts = opts or {}
	local depth = opts.depth or FX.crackDepth
	local thick = opts.thick or 2.2
	local grow  = opts.grow or 0.18
	local z     = opts.z or 118
	local sub   = opts.sub or FX.crackSub

	local target = origin + Vector2.new(math.cos(angle), math.sin(angle)) * length
	local pts = { origin }
	fracture(origin, target, depth, length * 0.26, pts)

	local group = newGroup(z)
	crackGroups[#crackGroups + 1] = group

	local segs = {}
	for i = 1, #pts - 1 do
		local a, b = pts[i], pts[i + 1]
		local t = thick * (1 - (i / #pts) * 0.55)
		local s = segment(group, a, b, t, WHITE, 2, 0.04)
		if s then segs[#segs + 1] = s end
	end

	for _, f in ipairs(segs) do f.Visible = false end
	chunkedReveal(segs, grow)

	if sub > 0 then
		for _ = 1, sub do
			local idx = math.random(2, math.max(#pts - 1, 2))
			task.delay(grow * (idx / #pts), function()
				crack(pts[idx], angle + math.rad(math.random(-75, 75)),
					length * (math.random(30, 55) / 100), {
						depth = math.max(depth - 1, 2),
						thick = thick * 0.6,
						grow  = grow * 0.7,
						z     = z - 1,
						sub   = sub - 1,
					})
			end)
		end
	end

	return pts
end

-- ══════════════════════════════════════
--  RING
-- ══════════════════════════════════════
local function ring(pos, maxSize, life, thickness, colour)
	local r = Instance.new("Frame")
	r.AnchorPoint = Vector2.new(0.5, 0.5)
	r.Position = UDim2.fromOffset(pos.X, pos.Y)
	r.Size = UDim2.fromOffset(10, 10)
	r.BackgroundTransparency = 1
	r.BorderSizePixel = 0
	r.ZIndex = 116
	r.Parent = Stage

	local c = Instance.new("UICorner")
	c.CornerRadius = UDim.new(1, 0)
	c.Parent = r

	local st = Instance.new("UIStroke")
	st.Color = colour or WHITE
	st.Thickness = thickness or 3
	st.Transparency = 0
	st.Parent = r

	Tween(r, life, { Size = UDim2.fromOffset(maxSize, maxSize) }, Enum.EasingStyle.Quint)
	Tween(st, life, { Transparency = 1, Thickness = 0.4 }, Enum.EasingStyle.Quad)
	task.delay(life + 0.1, function() if r.Parent then r:Destroy() end end)
end

-- ══════════════════════════════════════
--  SHARDS + SPARKS
-- ══════════════════════════════════════
local function shard(pos, size, angle, delay)
	local s = Instance.new("Frame")
	s.AnchorPoint = Vector2.new(0.5, 0.5)
	s.Position = UDim2.fromOffset(pos.X, pos.Y)
	s.Size = UDim2.fromOffset(size, size * (math.random(45, 130) / 100))
	local g = math.random(215, 255)
	s.BackgroundColor3 = Color3.fromRGB(g, g, g)
	s.BackgroundTransparency = 1
	s.Rotation = math.deg(angle) + math.random(-25, 25)
	s.BorderSizePixel = 0
	s.ZIndex = 126
	s.Parent = Stage

	local st = Instance.new("UIStroke")
	st.Color = WHITE
	st.Thickness = 1.4
	st.Transparency = 1
	st.Parent = s

	task.delay(delay, function()
		if not s.Parent then return end
		s.BackgroundTransparency = 0.06
		st.Transparency = 0

		local _, vh = viewport()
		local fall = math.random(11, 18) / 10
		local dist = 0.45 + math.random() * 0.5
		local drift = math.random(-130, 130)

		Tween(s, fall, {
			Position = UDim2.fromOffset(pos.X + drift, pos.Y + vh * dist),
			Rotation = s.Rotation + math.random(-300, 300),
			BackgroundTransparency = 1,
			Size = UDim2.fromOffset(size * 0.12, size * 0.12),
		}, Enum.EasingStyle.Quad, Enum.EasingDirection.In)

		Tween(st, fall * 0.6, { Transparency = 1 })
		task.delay(fall + 0.15, function() if s.Parent then s:Destroy() end end)
	end)
end

local function spark(pos, delay)
	local sz = math.random(2, 5)
	local p = Instance.new("Frame")
	p.AnchorPoint = Vector2.new(0.5, 0.5)
	p.Position = UDim2.fromOffset(pos.X, pos.Y)
	p.Size = UDim2.fromOffset(sz, sz)
	p.BackgroundColor3 = WHITE
	p.BackgroundTransparency = 1
	p.BorderSizePixel = 0
	p.ZIndex = 128
	p.Parent = Stage

	local c = Instance.new("UICorner")
	c.CornerRadius = UDim.new(1, 0)
	c.Parent = p

	task.delay(delay, function()
		if not p.Parent then return end
		p.BackgroundTransparency = 0
		local ang = math.random() * math.pi * 2
		local speed = math.random(180, 560)
		local life = math.random(6, 13) / 10
		Tween(p, life, {
			Position = UDim2.fromOffset(
				pos.X + math.cos(ang) * speed,
				pos.Y + math.sin(ang) * speed + 240),
			BackgroundTransparency = 1,
			Size = UDim2.fromOffset(1, 1),
		}, Enum.EasingStyle.Quad)
		task.delay(life + 0.15, function() if p.Parent then p:Destroy() end end)
	end)
end

-- ══════════════════════════════════════
--  FLASH + SHAKE
-- ══════════════════════════════════════
local Flash = Instance.new("Frame")
Flash.Size = UDim2.new(1, 0, 1, 0)
Flash.BackgroundColor3 = WHITE
Flash.BackgroundTransparency = 1
Flash.BorderSizePixel = 0
Flash.ZIndex = 190
Flash.Parent = ScreenGui

local function flash(peak, hold, fade, colour)
	Flash.BackgroundColor3 = colour or WHITE
	Flash.BackgroundTransparency = peak
	task.delay(hold, function()
		Tween(Flash, fade, { BackgroundTransparency = 1 }, Enum.EasingStyle.Quad)
	end)
end

local shakeAmount = 0
local shakeConn = RunService.RenderStepped:Connect(function()
	-- math.random needs integers, so floor before use
	local amt = math.floor(shakeAmount)
	if amt < 1 then
		if Stage.Position ~= UDim2.new() then Stage.Position = UDim2.new() end
		shakeAmount = 0
		return
	end
	Stage.Position = UDim2.fromOffset(math.random(-amt, amt), math.random(-amt, amt))
	shakeAmount = shakeAmount * 0.86
end)

local function shake(amount)
	shakeAmount = amount
end

-- ══════════════════════════════════════
--  INTRO CONTENT
-- ══════════════════════════════════════
local IntroImg = Instance.new("ImageLabel")
IntroImg.AnchorPoint = Vector2.new(0.5, 0.5)
IntroImg.Position = UDim2.new(0.5, 0, 0.5, 0)
IntroImg.Size = UDim2.fromOffset(190, 190)
IntroImg.BackgroundTransparency = 1
IntroImg.Image = LOGO_ID
IntroImg.ImageTransparency = 1
IntroImg.ScaleType = Enum.ScaleType.Fit
IntroImg.ZIndex = 140
IntroImg.Parent = Stage

local IntroScale = Instance.new("UIScale")
IntroScale.Scale = 1.3
IntroScale.Parent = IntroImg

local WelcomeMain = Instance.new("TextLabel")
WelcomeMain.AnchorPoint = Vector2.new(0.5, 0)
WelcomeMain.Position = UDim2.new(0.5, 0, 0.5, 108)
WelcomeMain.Size = UDim2.fromOffset(420, 24)
WelcomeMain.BackgroundTransparency = 1
WelcomeMain.Text = "Welcome To 2t1"
WelcomeMain.TextColor3 = Color3.fromRGB(240, 244, 255)
WelcomeMain.TextSize = 19
WelcomeMain.Font = Enum.Font.GothamBold
WelcomeMain.TextTransparency = 1
WelcomeMain.ZIndex = 140
WelcomeMain.Parent = Stage

local WelcomeStroke = Instance.new("UIStroke")
WelcomeStroke.Color = Color3.fromRGB(0, 0, 0)
WelcomeStroke.Thickness = 2
WelcomeStroke.Transparency = 0.65
WelcomeStroke.Parent = WelcomeMain

local WelcomeUser = Instance.new("TextLabel")
WelcomeUser.AnchorPoint = Vector2.new(0.5, 0)
WelcomeUser.Position = UDim2.new(0.5, 0, 0.5, 134)
WelcomeUser.Size = UDim2.fromOffset(420, 18)
WelcomeUser.BackgroundTransparency = 1
WelcomeUser.RichText = true
WelcomeUser.Text = string.format(
	'<font color="rgb(150,190,255)">@</font><font color="rgb(255,255,255)">%s</font>',
	LocalPlayer.Name)
WelcomeUser.TextSize = 13
WelcomeUser.Font = Enum.Font.GothamMedium
WelcomeUser.TextTransparency = 1
WelcomeUser.ZIndex = 140
WelcomeUser.Parent = Stage

local UserStroke = Instance.new("UIStroke")
UserStroke.Color = Color3.fromRGB(0, 0, 0)
UserStroke.Thickness = 2
UserStroke.Transparency = 0.7
UserStroke.Parent = WelcomeUser

-- ══════════════════════════════════════
--  SEQUENCE
-- ══════════════════════════════════════
local function playShatter()
	local w, h = viewport()
	local centre = Vector2.new(w * 0.5, h * 0.5)
	local edge = math.max(w, h)

	----------------------------------------------------------------
	-- 0  charge
	----------------------------------------------------------------
	shake(3)
	ring(centre, 200, 0.4, 1.6, ICEBLUE)
	task.wait(0.22)
	ring(centre, 120, 0.28, 2.2, WHITE)
	task.wait(0.14)

	----------------------------------------------------------------
	-- 1  strike
	----------------------------------------------------------------
	flash(0.06, 0.02, 0.16)
	shake(FX.shakeMax)

	-- the logo is caught in the flash for a moment
	IntroImg.ImageTransparency = 0.15
	task.delay(0.09, function()
		Tween(IntroImg, 0.22, { ImageTransparency = 1 })
	end)

	local burst = halo(Stage, centre, 460, WHITE, 0.5, 112)
	Tween(burst, 0.45, { BackgroundTransparency = 1, Size = UDim2.fromOffset(760, 760) })
	task.delay(0.5, function() if burst.Parent then burst:Destroy() end end)

	ring(centre, edge * 1.1, 0.7, 5, WHITE)

	for i = 1, FX.bolts do
		local ang = (i / FX.bolts) * math.pi * 2 + math.random() * 0.4
		local target = centre + Vector2.new(math.cos(ang), math.sin(ang)) * edge * 0.72
		bolt(centre, target, {
			core = math.random(20, 30) / 10,
			branches = FX.branches,
			reveal = 0.05,
			life = 0.38,
		})
	end

	for i = 1, 12 do spark(centre, i * 0.005) end

	task.wait(0.16)

	----------------------------------------------------------------
	-- 2  fracture
	----------------------------------------------------------------
	flash(0.36, 0, 0.2, ICEBLUE)
	shake(13)

	local tips = {}
	for i = 1, FX.cracks do
		local ang = (i / FX.cracks) * math.pi * 2 + math.random() * 0.5
		local pts = crack(centre, ang, edge * (math.random(48, 78) / 100), {
			thick = math.random(18, 26) / 10,
			grow = 0.16,
		})
		for _, p in ipairs(pts) do
			if math.random() < 0.45 then
				tips[#tips + 1] = { pos = p, ang = ang }
			end
		end
	end

	task.wait(0.26)

	-- a second, off-centre strike while the cracks are still spreading
	local off = centre + Vector2.new(
		math.random(-math.floor(w * 0.26), math.floor(w * 0.26)),
		math.random(-math.floor(h * 0.22), math.floor(h * 0.22)))

	flash(0.18, 0.01, 0.14)
	shake(15)
	ring(off, 420, 0.55, 3, WHITE)
	for _ = 1, math.max(1, FX.bolts - 3) do
		local ang = math.random() * math.pi * 2
		bolt(off, off + Vector2.new(math.cos(ang), math.sin(ang)) * edge * 0.45, {
			core = 2, branches = 1, reveal = 0.04, life = 0.34,
		})
	end
	for i = 1, 10 do spark(off, i * 0.005) end

	task.wait(0.32)

	----------------------------------------------------------------
	-- 3  shatter
	----------------------------------------------------------------
	flash(0, 0.03, 0.34)
	shake(FX.shakeMax + 5)
	ring(centre, edge * 1.45, 0.85, 6, WHITE)

	-- the cracks flare before the glass gives
	for _, g in ipairs(crackGroups) do
		if g and g.Parent then fadeGroup(g, 0.45) end
	end
	crackGroups = {}

	for i = 1, FX.shards do
		local t = tips[math.random(1, math.max(#tips, 1))]
		local pos = t and t.pos or centre
		local ang = t and t.ang or (math.random() * math.pi * 2)
		shard(pos + Vector2.new(math.random(-28, 28), math.random(-28, 28)),
			math.random(26, 88), ang, i * 0.007)
	end

	for i = 1, FX.sparks do
		local t = tips[math.random(1, math.max(#tips, 1))]
		spark(t and t.pos or centre, i * 0.005)
	end

	task.wait(0.6)

	----------------------------------------------------------------
	-- 4  settle
	----------------------------------------------------------------
	shake(4)

	IntroScale.Scale = 1.28
	Tween(IntroImg, 0.7, { ImageTransparency = 0 })
	Tween(IntroScale, 0.85, { Scale = 1 }, Enum.EasingStyle.Back)

	task.wait(0.22)
	Tween(WelcomeMain, 0.55, { TextTransparency = 0.02 })
	task.wait(0.08)
	Tween(WelcomeUser, 0.55, { TextTransparency = 0.12 })

	task.wait(1.2)

	Tween(IntroImg, 0.45, { ImageTransparency = 1 })
	Tween(IntroScale, 0.5, { Scale = 1.12 })
	Tween(WelcomeMain, 0.4, { TextTransparency = 1 })
	Tween(WelcomeUser, 0.4, { TextTransparency = 1 })
	Tween(WelcomeStroke, 0.4, { Transparency = 1 })
	Tween(UserStroke, 0.4, { Transparency = 1 })

	task.wait(0.5)

	for _, c in ipairs(Stage:GetChildren()) do
		if c ~= IntroImg and c ~= WelcomeMain and c ~= WelcomeUser then
			c:Destroy()
		end
	end
end

-- ══════════════════════════════════════
--  NOTIFICATION CARD
-- ══════════════════════════════════════
local Notif = Instance.new("Frame")
Notif.AnchorPoint = Vector2.new(1, 1)
Notif.Position = UDim2.new(1, -20, 1, 120)
Notif.Size = UDim2.fromOffset(300, 78)
Notif.BackgroundTransparency = 1
Notif.BorderSizePixel = 0
Notif.ZIndex = 250
Notif.Parent = ScreenGui
Instance.new("UICorner", Notif).CornerRadius = UDim.new(0, 10)

local NotifStroke = Instance.new("UIStroke")
NotifStroke.Color = WHITE
NotifStroke.Thickness = 1.2
NotifStroke.Transparency = 1
NotifStroke.ApplyStrokeMode = Enum.ApplyStrokeMode.Border
NotifStroke.Parent = Notif

local NotifLogo = Instance.new("ImageLabel")
NotifLogo.Position = UDim2.new(0, 14, 0.5, -20)
NotifLogo.Size = UDim2.fromOffset(40, 40)
NotifLogo.BackgroundTransparency = 1
NotifLogo.Image = LOGO_ID
NotifLogo.ImageTransparency = 1
NotifLogo.ScaleType = Enum.ScaleType.Fit
NotifLogo.ZIndex = 251
NotifLogo.Parent = Notif

local function notifText(y, size, font, colour, bold)
	local t = Instance.new("TextLabel")
	t.Position = UDim2.new(0, 62, 0, y)
	t.Size = UDim2.new(1, -72, 0, size + 4)
	t.BackgroundTransparency = 1
	t.TextColor3 = colour
	t.TextSize = size
	t.Font = font
	t.TextXAlignment = Enum.TextXAlignment.Left
	t.TextTransparency = 1
	t.ZIndex = 251
	t.Parent = Notif

	local st = Instance.new("UIStroke")
	st.Color = Color3.fromRGB(0, 0, 0)
	st.Thickness = bold and 1.5 or 1.2
	st.Transparency = bold and 0.6 or 0.7
	st.Parent = t
	return t
end

local NotifTitle = notifText(12, 11, Enum.Font.GothamBold, Color3.fromRGB(160, 200, 255), true)
NotifTitle.Text = "GAME DETECTED"

local NotifDiv = Instance.new("Frame")
NotifDiv.Position = UDim2.new(0, 62, 0, 32)
NotifDiv.Size = UDim2.new(1, -78, 0, 1)
NotifDiv.BackgroundColor3 = WHITE
NotifDiv.BackgroundTransparency = 1
NotifDiv.BorderSizePixel = 0
NotifDiv.ZIndex = 251
NotifDiv.Parent = Notif

local NotifHub = notifText(37, 14, Enum.Font.GothamBold, WHITE, true)
NotifHub.Text = "2t1"

local NotifGame = notifText(55, 11, Enum.Font.GothamMedium, SOFT, false)
NotifGame.Text = ""

local function ShowNotif(gameName)
	NotifGame.Text = gameName
	Notif.Position = UDim2.new(1, -20, 1, 120)

	Tween(Notif, 0.5, { Position = UDim2.new(1, -20, 1, -20) }, Enum.EasingStyle.Back)
	Tween(NotifStroke, 0.4, { Transparency = 0.4 })
	task.wait(0.15)
	Tween(NotifLogo, 0.3, { ImageTransparency = 0 })
	Tween(NotifTitle, 0.3, { TextTransparency = 0 })
	task.wait(0.08)
	Tween(NotifDiv, 0.25, { BackgroundTransparency = 0.6 })
	task.wait(0.08)
	Tween(NotifHub, 0.3, { TextTransparency = 0 })
	Tween(NotifGame, 0.3, { TextTransparency = 0.15 })
end

local function HideNotif()
	Tween(Notif, 0.4, { Position = UDim2.new(1, -20, 1, 120) },
		Enum.EasingStyle.Quad, Enum.EasingDirection.In)
	Tween(NotifStroke, 0.3, { Transparency = 1 })
	Tween(NotifLogo, 0.3, { ImageTransparency = 1 })
	Tween(NotifTitle, 0.3, { TextTransparency = 1 })
	Tween(NotifDiv, 0.3, { BackgroundTransparency = 1 })
	Tween(NotifHub, 0.3, { TextTransparency = 1 })
	Tween(NotifGame, 0.3, { TextTransparency = 1 })
end

-- ══════════════════════════════════════
--  GAME DATABASE
-- ══════════════════════════════════════
local GameNames = {
	[13772394625] = "Blade Ball",
	[16732694052] = "Fisch",
	[2753915549]  = "Blox Fruits",
	[142823291]   = "MM2",
	[286090429]   = "Arsenal",
	[2788229376]  = "Da Hood",
}

local GameScripts = {
	[123974602339071] = "https://raw.githubusercontent.com/JSInvasor/2t1StudioUI/refs/heads/main/test.lua",
	[16732694052] = "https://raw.githubusercontent.com/JSInvasor/EpsilonOmega/main/games/fisch.lua",
	[2753915549]  = "https://raw.githubusercontent.com/JSInvasor/EpsilonOmega/main/games/bloxfruits.lua",
	[142823291]   = "https://raw.githubusercontent.com/JSInvasor/EpsilonOmega/main/games/mm2.lua",
	[286090429]   = "https://raw.githubusercontent.com/JSInvasor/EpsilonOmega/main/games/arsenal.lua",
	[2788229376]  = "https://raw.githubusercontent.com/JSInvasor/EpsilonOmega/main/games/dahood.lua",
}

local UniversalScript = "https://raw.githubusercontent.com/JSInvasor/EpsilonOmega/main/hub.lua"

-- ══════════════════════════════════════
--  MAIN
-- ══════════════════════════════════════
local scriptResult = nil

local function cleanup()
	if shakeConn then
		shakeConn:Disconnect()
		shakeConn = nil
	end
	if ScreenGui and ScreenGui.Parent then
		ScreenGui:Destroy()
	end
end

local function run()
	playShatter()

	local gameName = GameNames[game.PlaceId] or "Universal"
	local scriptUrl = GameScripts[game.PlaceId] or UniversalScript

	ShowNotif(gameName)

	local ok, res = pcall(function() return game:HttpGet(scriptUrl) end)

	if not ok or not res or #res < 10 then
		NotifTitle.Text = "DOWNLOAD FAILED"
		NotifTitle.TextColor3 = RED
		Tween(NotifStroke, 0.2, { Color = RED })
		task.wait(3)
		HideNotif()
		task.wait(0.5)
		cleanup()
		return
	end

	local fn = loadstring(res)
	if not fn then
		NotifTitle.Text = "SCRIPT ERROR"
		NotifTitle.TextColor3 = RED
		Tween(NotifStroke, 0.2, { Color = RED })
		task.wait(3)
		HideNotif()
		task.wait(0.5)
		cleanup()
		return
	end

	scriptResult = fn

	NotifTitle.Text = "READY"
	NotifTitle.TextColor3 = GREEN
	Tween(NotifStroke, 0.3, { Color = GREEN })

	task.wait(2)
	HideNotif()
	task.wait(0.5)
	cleanup()
end

run()

if scriptResult then
	local ok, err = pcall(scriptResult)
	if not ok then
		warn("[2t1] Runtime error: " .. tostring(err))
	end
end

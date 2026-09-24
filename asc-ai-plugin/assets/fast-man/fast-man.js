window.fastManMaps = {
	"desktop": [
		"#########################",
		"#o....#...........#....o#",
		"#.###.#.###.#.###.#.###.#",
		"#.....#.....#.....#.....#",
		"##.#.##.#.#####.#.##.#.##",
		"...#....#.......#....#...",
		"##.#.## #### #### ##.#.##",
		"#..#.##C C C C C C##.#..#",
		"#.##.###############.##.#",
		"#...........B...........#",
		"####.##.####.####.##.####",
		".....##.#.......#.##.....",
		"####.##.#.#####.#.##.####",
		"#.......#...#...#.......#",
		"#.##.##.###.#.###.##.##.#",
		"#o...##.....P.....##...o#",
		"#########################"
	],
	"phone": [
		"             ",
		"#############",
		"#o...###...o#",
		"#.##.....##.#",
		"#.##.###.##.#",
		"#....###....#",
		"##.#######.##",
		"##.........##",
		"##.## # ##.##",
		"##.#CC#CC#.##",
		"...#######...",
		"##.##...##.##",
		"##....#....##",
		"##.#######.##",
		"#.....B.....#",
		"#.####.####.#",
		"#.##.....##.#",
		"#.##.###.##.#",
		"#o....P....o#",
		"#############",
		"             "
	]
};

// Round one preserves the original editable layouts above. Rounds two through five follow.
window.fastManMapSets = {
	desktop: [window.fastManMaps.desktop,
	[
		"#########################",
		"#o..#.....#...#.....#..o#",
		"#.#.#.###.#.#.#.###.#.#.#",
		"#...#.#.....#.....#.#...#",
		"#.###.#.#########.#.###.#",
		".........................",
		"#.########## ##########.#",
		"#.#... C C C C C C ...#.#",
		"#.#.### # ##### # ###.#.#",
		"#.#.#...#...B...#...#.#.#",
		"#.#.#.#####.#.#####.#.#.#",
		"..#.#.#...#...#...#.#.#..",
		"#.#.#.#.#.#.#.#.#.#.#.#.#",
		"#...#.#.....#.....#.#...#",
		"#.###.#####.#.#####.###.#",
		"#o..........P..........o#",
		"#########################"
	],
	[
		"#########################",
		"#o..#...#.......#...#..o#",
		"#.#.#.#.#.#####.#.#.#.#.#",
		"#...#.#.#...#...#.#.#...#",
		"#.###.#.#.#.#.#.#.#.###.#",
		"......#...#...#...#......",
		"#.##### ######### #####.#",
		"#.#... C C C C C C ...#.#",
		"#.#.##### # # # #####.#.#",
		"#.#.....#.#.B.#.#.....#.#",
		"#.###.#.#.#.#.#.#.#.###.#",
		"......#.#...#...#.#......",
		"#.#####.###.#.###.#####.#",
		"#.....#...#.#.#...#.....#",
		"#.###.#.#.#.#.#.#.#.###.#",
		"#o......#...P...#......o#",
		"#########################"
	],
	[
		"#########################",
		"#o..#.....#...#.....#..o#",
		"#.#.#.###.#.#.#.###.#.#.#",
		"#.#...#.....#.....#...#.#",
		"#.#####.#########.#####.#",
		"....#...............#....",
		"#.#.#.#############.#.#.#",
		"#.#... C C C C C C ...#.#",
		"#.#.##### # # # #####.#.#",
		"#.#.......#.B.#.......#.#",
		"#.#########.#.#########.#",
		"............#............",
		"#.#####.#########.#####.#",
		"#.#.....#.......#.....#.#",
		"#.#.#.#.#.#####.#.#.#.#.#",
		"#o..#.......P.......#..o#",
		"#########################"
	],
	[
		"#########################",
		"#o......#...#...#......o#",
		"#.###.#.#.#.#.#.#.#.###.#",
		"#.#...#.#.#.#.#.#.#...#.#",
		"#.#.###.#.#.#.#.#.###.#.#",
		"..........#...#..........",
		"#.### ############# ###.#",
		"#...#  C C C C C C  #...#",
		"#.#.##### ##### #####.#.#",
		"#.#.........B.........#.#",
		"#.#.###.#########.###.#.#",
		"..#...#.#.......#.#...#..",
		"#.#.#.#.#.#####.#.#.#.#.#",
		"#.#.....#.#...#.#.....#.#",
		"#.###.###.#.#.#.###.###.#",
		"#o..........P..........o#",
		"#########################"
	]
	],
	phone: [window.fastManMaps.phone,
	[
		"             ",
		"#############",
		"#o....#....o#",
		"#.###.#.###.#",
		"#.#.......#.#",
		"#...#.#.#...#",
		"#.###.#.###.#",
		"#.....#.....#",
		"#.# ##### #.#",
		"#.#C C C C#.#",
		"#.#########.#",
		"..#...#...#..",
		"#.#.#.#.#.#.#",
		"#.#...B...#.#",
		"#.###.#.###.#",
		"#...........#",
		"#.###.#.###.#",
		"#.###.#.###.#",
		"#o....P....o#",
		"#############",
		"             "
	],
	[
		"             ",
		"#############",
		"#o.........o#",
		"#.###.#.###.#",
		"#.###.#.###.#",
		"#.#...#...#.#",
		"#.#.#.#.#.#.#",
		"#...#.#.#...#",
		"#.### # ###.#",
		"#.#C C C C#.#",
		"#.### # ###.#",
		"......#......",
		"#.#.#####.#.#",
		"#.#...B...#.#",
		"#.###.#.###.#",
		"#.#...#...#.#",
		"#.#.#.#.#.#.#",
		"#.#.#.#.#.#.#",
		"#o....P....o#",
		"#############",
		"             "
	],
	[
		"             ",
		"#############",
		"#o..#...#..o#",
		"#.#.#.#.#.#.#",
		"#...#.#.#...#",
		"#.#.#.#.#.#.#",
		"#.#...#...#.#",
		"#...#.#.#...#",
		"#.# # # # #.#",
		"#.#C C C C#.#",
		"#.#### ####.#",
		".....#.#.....",
		"#.##.#.#.##.#",
		"#.....B.....#",
		"#.#########.#",
		"#...#...#...#",
		"#.#...#...#.#",
		"#.#.#.#.#.#.#",
		"#o..#.P.#..o#",
		"#############",
		"             "
	],
	[
		"             ",
		"#############",
		"#o....#....o#",
		"#.###.#.###.#",
		"#.#...#...#.#",
		"#...#...#...#",
		"#.#########.#",
		"#...........#",
		"#.#########.#",
		"#. C C C C .#",
		"#.#########.#",
		"..#...#...#..",
		"#.#.#.#.#.#.#",
		"#.#.#.B.#.#.#",
		"#.#.#.#.#.#.#",
		"#.....#.....#",
		"#.#.#####.#.#",
		"#.#.#####.#.#",
		"#o....P....o#",
		"#############",
		"             "
	]
	]
};
window.fastManMapForRound = function (type, round) {
	var maps = window.fastManMapSets[type];
	return maps[(Math.max(1, round || 1) - 1) % maps.length];
};
window.fastManMapThemeForRound = function (round) {
	var themes = ['green', 'blue', 'purple', 'red', 'red'];
	return themes[(Math.max(1, round || 1) - 1) % themes.length];
};

window.asc_fm_maps = window.fastManMaps;
window.asc_fm_map_sets = window.fastManMapSets;
window.asc_fm_map_for_round = window.fastManMapForRound;
window.asc_fm_map_theme_for_round = window.fastManMapThemeForRound;
(function () {
	'use strict';
	var directions = { up: [0, -1], right: [1, 0], down: [0, 1], left: [-1, 0] };
	var opposite = { up: 'down', down: 'up', left: 'right', right: 'left' };
	var botProfiles = [
		{ name: 'Crawler', color: 'blue' },
		{ name: 'Scanner', color: 'blue' },
		{ name: 'Spammer', color: 'purple' },
		{ name: 'Hacker', color: 'purple' },
		{ name: 'Linker', color: 'green' },
		{ name: 'Miner', color: 'green' }
	];
	var phoneBotProfiles = [
		{ name: 'Crawler', color: 'blue' },
		{ name: 'Spammer', color: 'purple' },
		{ name: 'Scanner', color: 'blue' },
		{ name: 'Hacker', color: 'purple' }
	];
	var botIntervals = {
		easy: { blue: 600, purple: 500, green: 475 },
		normal: { blue: 550, purple: 450, green: 425 },
		hard: { blue: 500, purple: 400, green: 375 }
	};
	function Run(rows, type, round, difficulty) {
		this.rows = rows.map(function (row) { return Array.from(row); });
		this.type = type;
		this.width = rows[0].length;
		this.height = rows.length;
		this.round = round || 1;
		this.interval = 250;
		this.powerDuration = Math.max(6000, 15000 - (this.round - 1) * 1000);
		this.warningDuration = Math.max(3000, Math.min(6000, this.powerDuration / 2));
		var baseBots = type === 'phone' ? 2 : 4;
		var maxBots = type === 'phone' ? 4 : 6;
		var addedBots = this.round >= 5 ? 2 : (this.round >= 3 ? 1 : 0);
		this.botLimit = Math.min(maxBots, baseBots + addedBots);
		var profiles = type === 'phone' ? phoneBotProfiles : botProfiles;
		this.lives = 3;
		this.over = false;
		this.power = 0;
		this.combo = 0;
		this.recovery = 0;
		this.playerClock = 0;
		this.playerMotion = null;
		this.pendingCollection = null;
		this.agents = [];
		this.score = 0;
		this.nextLifeScore = 20000;
		this.bonusClock = 0;
		this.bonus = null;
		this.bonusSpot = null;
		this.bonusSpawns = 0;
		this.tagEvents = [];
		this.remaining = 0;
		this.direction = null;
		this.queued = null;
		this.queuedAlternatives = [];
		this.complete = false;
		this.rows.forEach(function (row, y) {
			row.forEach(function (symbol, x) {
				if (symbol === 'P') { this.x = x; this.y = y; this.start = { x: x, y: y }; }
				if (symbol === 'B') { this.bonusSpot = { x: x, y: y }; }
				if (symbol === 'C' && this.agents.length < this.botLimit) {
					var profile = profiles[this.agents.length];
					this.agents.push({ x: x, y: y, start: { x: x, y: y }, name: profile.name, color: profile.color, clock: 0, departing: true, exitOffset: this.agents.length, direction: null, wait: this.agents.length * 600, vulnerable: false });
				}
				if (symbol === '.' || symbol === 'o') { this.remaining++; }
			}, this);
		}, this);
		this.setDifficulty(difficulty || 'normal');
	}
	Run.prototype.setDifficulty = function (difficulty) {
		if (!Object.hasOwn(botIntervals, difficulty)) { difficulty = 'normal'; }
		var previousInterval = this.interval;
		var previousDifficulty = this.difficulty || difficulty;
		var previousAgentIntervals = this.agents.map(function (agent) { return this.agentInterval(agent, previousDifficulty); }, this);
		this.difficulty = difficulty;
		this.interval = 250;
		var playerScale = this.interval / previousInterval;
		function scaleMotion(motion, scale) {
			if (motion) { motion.duration *= scale; motion.remaining *= scale; }
		}
		this.playerClock *= playerScale;
		scaleMotion(this.playerMotion, playerScale);
		this.agents.forEach(function (agent, index) {
			var botScale = this.agentInterval(agent) / previousAgentIntervals[index];
			agent.clock *= botScale;
			if (agent.moveDuration) { agent.moveDuration *= botScale; }
			scaleMotion(agent.motion, botScale);
		}, this);
	};
	Run.prototype.queue = function (direction) {
		var choices = Array.isArray(direction) ? direction : [direction];
		choices = choices.filter(function (choice) { return Object.hasOwn(directions, choice); });
		if (choices.length && this.reverse(choices[0])) { return; }
		if (choices.length) { this.queued = choices[0]; this.queuedAlternatives = choices.slice(1); }
	};
	Run.prototype.reverse = function (direction) {
		if (!this.playerMotion || opposite[this.direction] !== direction) { return false; }
		var motion = this.playerMotion;
		var position = this.motionPosition(this, motion);
		var progress = 1 - motion.remaining / motion.duration;
		var duration = Math.max(1, motion.duration * progress);
		var sourceX = ((Math.round(motion.fromX) % this.width) + this.width) % this.width;
		this.x = sourceX;
		this.y = Math.round(motion.fromY);
		this.direction = direction;
		this.queued = null;
		this.queuedAlternatives = [];
		this.pendingCollection = null;
		this.playerMotion = { fromX: position.x, fromY: position.y, toX: motion.fromX, toY: motion.fromY, remaining: duration, duration: duration };
		this.playerClock = this.interval - duration;
		return true;
	};
	Run.prototype.agentInterval = function (agent, difficulty) {
		var intervals = botIntervals[difficulty || this.difficulty] || botIntervals.normal;
		var interval = intervals[agent.color] || intervals.blue;
		if (agent.vulnerable) { interval /= 0.8; }
		return interval;
	};
	Run.prototype.addScore = function (points) {
		this.score += points;
		var lives = 0;
		while (this.score >= this.nextLifeScore) {
			this.lives++;
			lives++;
			this.nextLifeScore += 40000;
		}
		return lives;
	};
	Run.prototype.bonusDetails = function () {
		var names = ['WordPress', 'Gemini', 'ChatGPT', 'Nginx', 'AWS'];
		var index = (this.round - 1) % names.length;
		return { name: names[index], points: Math.min(8000, 500 * Math.pow(2, this.round - 1)) };
	};
	Run.prototype.spawnBonus = function () {
		if (!this.bonusSpot || this.bonusSpawns >= 2 || this.bonus) { return null; }
		var details = this.bonusDetails();
		this.bonus = { x: this.bonusSpot.x, y: this.bonusSpot.y, name: details.name, points: details.points };
		this.bonusSpawns++;
		this.bonusClock = 0;
		return this.bonus;
	};
	Run.prototype.clearPower = function () {
		this.power = 0;
		this.combo = 0;
		this.agents.forEach(function (agent) { agent.vulnerable = false; });
	};
	Run.prototype.destination = function (direction, origin) {
		if (!direction) { return null; }
		var source = origin || this;
		var delta = directions[direction];
		var x = source.x + delta[0];
		var y = source.y + delta[1];
		if (y < 0 || y >= this.height) { return null; }
		if (this.type === 'phone' && (y === 0 || y === this.height - 1)) { return null; }
		if (x < 0 || x >= this.width) {
			if (this.rows[y][0] === '#' || this.rows[y][this.width - 1] === '#') { return null; }
			x = (x + this.width) % this.width;
		}
		if (this.rows[y][x] === '#') { return null; }
		return { x: x, y: y };
	};
	Run.prototype.collect = function (x, y) {
		var collected = this.rows[y][x];
		var points = 0;
		switch (collected) {
			case '.': points = 10; this.remaining--; break;
			case 'o':
				points = 50;
				this.remaining--;
				this.power = this.powerDuration;
				this.combo = 0;
				this.agents.forEach(function (agent) { agent.direction = null; agent.vulnerable = true; });
				break;
		}
		this.rows[y][x] = ' ';
		var bonus = null;
		if (this.bonus && this.bonus.x === x && this.bonus.y === y) {
			bonus = this.bonus;
			points += bonus.points;
			this.bonus = null;
			this.bonusClock = 0;
		}
		var lives = 0;
		if (points) { lives = this.addScore(points); }
		if (this.remaining === 0) { this.clearPower(); this.complete = true; }
		return { x: x, y: y, collected: collected, bonus: bonus, points: points, lives: lives };
	};
	Run.prototype.step = function (deferred) {
		if (this.complete || this.over || this.recovery > 0) { return null; }
		var target = null;
		var queuedDirections = [this.queued].concat(this.queuedAlternatives);
		for (var queuedDirection of queuedDirections) {
			target = this.destination(queuedDirection);
			if (target) { this.direction = queuedDirection; this.queued = null; this.queuedAlternatives = []; break; }
		}
		if (!target) { target = this.destination(this.direction); }
		if (!target) { return null; }
		var wrapped = false;
		if (Math.abs(target.x - this.x) > 1) { wrapped = true; }
		var fromX = this.x;
		var fromY = this.y;
		this.x = target.x;
		this.y = target.y;
		if (deferred) {
			var toX = this.x;
			if (wrapped && this.x > fromX) { toX -= this.width; }
			if (wrapped && this.x < fromX) { toX += this.width; }
			this.playerMotion = { fromX: fromX, fromY: fromY, toX: toX, toY: this.y, remaining: this.interval, duration: this.interval };
			this.pendingCollection = { x: this.x, y: this.y, direction: this.direction, threshold: 0.5, crossed: false };
			return { x: this.x, y: this.y, direction: this.direction, collected: null, wrapped: wrapped };
		}
		var result = this.collect(this.x, this.y);
		result.direction = this.direction;
		result.wrapped = wrapped;
		return result;
	};
	Run.prototype.motionPosition = function (entity, motion) {
		if (!motion) { return { x: entity.x, y: entity.y }; }
		var progress = 1 - motion.remaining / motion.duration;
		var x = motion.fromX + (motion.toX - motion.fromX) * progress;
		return { x: x, y: motion.fromY + (motion.toY - motion.fromY) * progress };
	};
	Run.prototype.passedCollectionThreshold = function () {
		if (!this.playerMotion || !this.pendingCollection || this.pendingCollection.crossed) { return false; }
		var motion = this.playerMotion;
		var position = this.motionPosition(this, motion);
		var threshold = this.pendingCollection.threshold;
		switch (this.pendingCollection.direction) {
			case 'right': return position.x >= motion.fromX + threshold;
			case 'left': return position.x <= motion.fromX - threshold;
			case 'down': return position.y >= motion.fromY + threshold;
			case 'up': return position.y <= motion.fromY - threshold;
		}
		return false;
	};
	Run.prototype.collisionDistance = function (agent) {
		var player = this.motionPosition(this, this.playerMotion);
		var bot = this.motionPosition(agent, agent.motion);
		var dx = Math.abs(player.x - bot.x);
		dx = Math.min(dx, Math.abs(dx - this.width), Math.abs(dx + this.width));
		return Math.hypot(dx, player.y - bot.y);
	};
	Run.prototype.collisions = function () {
		for (var index = 0; index < this.agents.length; index++) {
			var agent = this.agents[index];
			if (agent.wait > 0 || this.collisionDistance(agent) > 0.52) { continue; }
			if (agent.vulnerable) {
				var combo = this.combo++;
				var points = 200 * Math.pow(2, Math.min(combo, 3));
				var lives = this.addScore(points);
				this.tagEvents.push({ type: 'tag', points: points, x: agent.x, y: agent.y, index: index, combo: combo, lives: lives });
				agent.x = agent.start.x; agent.y = agent.start.y;
				agent.wait = 3000; agent.direction = null; agent.vulnerable = false;
				agent.clock = 0; agent.departing = true; agent.motion = null;
				continue;
			}
			this.lives--;
			this.complete = false;
			this.clearPower();
			this.direction = null; this.queued = null; this.queuedAlternatives = [];
			this.playerMotion = null; this.pendingCollection = null;
			if (this.lives === 0) { this.over = true; this.complete = false; return 'over'; }
			this.x = this.start.x; this.y = this.start.y;
			this.agents.forEach(function (item, index) {
				item.x = item.start.x; item.y = item.start.y;
				item.direction = null; item.wait = index * 600;
				item.clock = 0; item.departing = true; item.motion = null;
			});
			this.recovery = 2000; this.playerClock = 0;
			return 'life';
		}
		return null;
	};
	Run.prototype.distances = function () {
		var distances = new Map();
		var queue = [{ x: this.x, y: this.y }];
		distances.set(this.y * this.width + this.x, 0);
		for (var point of queue) {
			var distance = distances.get(point.y * this.width + point.x);
			for (var direction of Object.keys(directions)) {
				var target = this.destination(direction, point);
				if (!target) { continue; }
				var index = target.y * this.width + target.x;
				if (!distances.has(index)) { distances.set(index, distance + 1); queue.push(target); }
			}
		}
		return distances;
	};
	Run.prototype.moveAgents = function (onlyIndex) {
		var distances = this.distances();
		var order = Object.keys(directions);
		var opposite = { up: 'down', down: 'up', left: 'right', right: 'left' };
		for (var index = 0; index < this.agents.length; index++) {
			if (onlyIndex !== undefined && index !== onlyIndex) { continue; }
			var agent = this.agents[index];
			if (agent.wait > 0) { continue; }
			var options = [];
			for (var offset = 0; offset < order.length; offset++) {
				var direction = order[(offset + index) % order.length];
				var target = this.destination(direction, agent);
				if (target) { options.push({ direction: direction, x: target.x, y: target.y, distance: distances.get(target.y * this.width + target.x) }); }
			}
			var forward = options.filter(function (option) { return option.direction !== opposite[agent.direction]; });
			if (forward.length) { options = forward; }
			var fleeing = agent.vulnerable;
			options.sort(function (a, b) {
				if (fleeing) { return b.distance - a.distance; }
				return a.distance - b.distance;
			});
			if (options.length) {
				var choice = options[0];
				if (agent.departing && options.length > 1) {
					var openChoices = options.filter(function (option) {
						var onward = this.destination(option.direction, { x: option.x, y: option.y });
						return onward !== null;
					}, this);
					if (openChoices.length) { options = openChoices; }
					choice = options[agent.exitOffset % options.length];
				}
				agent.departing = false;
				var fromX = agent.x;
				var fromY = agent.y;
				var toX = choice.x;
				if (Math.abs(choice.x - fromX) > 1 && choice.x > fromX) { toX -= this.width; }
				if (Math.abs(choice.x - fromX) > 1 && choice.x < fromX) { toX += this.width; }
				agent.x = choice.x; agent.y = choice.y; agent.direction = choice.direction;
				agent.moveDuration = this.agentInterval(agent);
				agent.motion = { fromX: fromX, fromY: fromY, toX: toX, toY: choice.y, remaining: agent.moveDuration, duration: agent.moveDuration };
			}
		}
	};
	Run.prototype.advance = function (elapsed) {
		var events = [];
		if (this.over || this.complete) { return events; }
		if (this.recovery > 0) { this.recovery = Math.max(0, this.recovery - elapsed); return events; }
		if (this.playerMotion) {
			this.playerMotion.remaining = Math.max(0, this.playerMotion.remaining - elapsed);
			if (this.pendingCollection && (this.passedCollectionThreshold() || this.playerMotion.remaining === 0)) {
				this.pendingCollection.crossed = true;
				var collection = this.collect(this.pendingCollection.x, this.pendingCollection.y);
				this.pendingCollection = null;
				events.push({ type: 'collect', move: collection });
				if (collection.bonus) { events.push({ type: 'bonus', bonus: collection.bonus }); }
				if (collection.lives) { events.push({ type: 'extra-life', lives: collection.lives }); }
				if (this.complete) { this.playerMotion = null; events.push({ type: 'clear' }); return events; }
			}
			if (this.playerMotion.remaining === 0) { this.playerMotion = null; }
		}
		this.agents.forEach(function (agent) {
			if (!agent.motion) { return; }
			agent.motion.remaining = Math.max(0, agent.motion.remaining - elapsed);
			if (agent.motion.remaining === 0) { agent.motion = null; }
		});
		var hadPower = this.power > 0;
		this.power = Math.max(0, this.power - elapsed);
		if (hadPower && this.power === 0) { this.agents.forEach(function (agent) { agent.vulnerable = false; }); }
		this.bonusClock += elapsed;
		if (!this.bonus && this.bonusSpawns < 2 && this.bonusClock >= 25000) {
			var bonus = this.spawnBonus();
			if (bonus) { events.push({ type: 'bonus-spawn', bonus: bonus }); }
		}
		this.agents.forEach(function (agent) {
			agent.clock += Math.max(0, elapsed - agent.wait);
			agent.wait = Math.max(0, agent.wait - elapsed);
		});
		var collision = this.collisions();
		events.push.apply(events, this.tagEvents.splice(0));
		if (collision) { events.push({ type: collision }); return events; }
		this.playerClock += elapsed;
		if (this.playerClock >= this.interval) {
			this.playerClock %= this.interval;
			var move = this.step(true);
			if (move) { events.push({ type: 'move', move: move }); }
			collision = this.collisions();
			events.push.apply(events, this.tagEvents.splice(0));
			if (collision) { events.push({ type: collision }); return events; }
		}
		for (var index = 0; index < this.agents.length; index++) {
			var agent = this.agents[index];
			var agentInterval = this.agentInterval(agent);
			if (agent.wait > 0 || agent.clock < agentInterval) { continue; }
			agent.clock %= agentInterval;
			this.moveAgents(index);
			collision = this.collisions();
			events.push.apply(events, this.tagEvents.splice(0));
			if (collision) { events.push({ type: collision }); return events; }
		}
		return events;
	};
	window.FastManRun = Run;
	window.asc_fm_Run = Run;
	window.asc_fm_run = Run;
}());

if (typeof document !== 'undefined') {
	(function () {
		'use strict';
		var key = 'asc-fast-man-high-score-v1';
		var assetBase = (typeof window !== 'undefined' && window.asc_fm_config && window.asc_fm_config.asset_url) || 'assets/';

		var fast = false;
		var mazeType = '';
		var highScore = 0;
		var storageAvailable = true;
		var run;
		var playing = false;
		var started = false;
		var lastFrame = null;
		var roundCarry = null;
		var cells = [];
		var tunnelCopies = new Map();
		function getAssetUrl(file) {
			var base = (typeof window !== 'undefined' && window.asc_fm_config && window.asc_fm_config.asset_url) || assetBase || 'assets/';
			if (base.charAt(base.length - 1) !== '/') { base += '/'; }
			return base + file;
		}
		function botImageSource(botName) {
			return getAssetUrl('128-bot-' + botName.toLowerCase() + '.png');
		}
		var botImages = {
			Crawler: getAssetUrl('128-bot-crawler.png'),
			Spammer: getAssetUrl('128-bot-spammer.png'),
			Linker: getAssetUrl('128-bot-linker.png'),
			Scanner: getAssetUrl('128-bot-scanner.png'),
			Hacker: getAssetUrl('128-bot-hacker.png'),
			Miner: getAssetUrl('128-bot-miner.png')
		};
		var deathMessage = 'Website Hacked!';
		var rewardMessage = 'Website Upgraded!';
		var tagMessage = 'Website Secured!';
		var completeMessages = ['Sub-Second Page Loads!', 'Layered WordPress Security!', 'Website Visibility Optimized!'];
		var completeMessageIndex = 0;
		var timedMessageTimer = null;
		var messageDuration = 2500;
		var transition = null;
		var transitionTimer = null;
		var transitionReady = false;
		var transitionDuration = 1000;
		var inCutscene = false;
		var cutsceneTimer = null;
		var cutsceneFadeTimer = null;
		var cutsceneCallback = null;
		var sfxEnabled = false;
		var musicEnabled = false;
		var audioUnlocked = false;

		var CyberSoundFX = {
			ctx: null,
			init: function () {
				if (!this.ctx && typeof window !== 'undefined') {
					var AudioCtx = window.AudioContext || window.webkitAudioContext;
					if (AudioCtx) {
						this.ctx = new AudioCtx();
					}
				}
				if (this.ctx && this.ctx.state === 'suspended') {
					this.ctx.resume().catch(function () {});
				}
			},
			playWaka: function () {
				if (!sfxEnabled) { return; }
				this.init();
				if (!this.ctx) { return; }
				var osc = this.ctx.createOscillator();
				var gain = this.ctx.createGain();
				osc.type = 'triangle';
				osc.frequency.setValueAtTime(400, this.ctx.currentTime);
				osc.frequency.exponentialRampToValueAtTime(80, this.ctx.currentTime + 0.05);
				gain.gain.setValueAtTime(0.15, this.ctx.currentTime);
				gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + 0.05);
				osc.connect(gain);
				gain.connect(this.ctx.destination);
				osc.start();
				osc.stop(this.ctx.currentTime + 0.05);
			},
			playPowerUp: function () {
				if (!sfxEnabled) { return; }
				this.init();
				if (!this.ctx) { return; }
				var osc = this.ctx.createOscillator();
				var gain = this.ctx.createGain();
				osc.type = 'sawtooth';
				osc.frequency.setValueAtTime(150, this.ctx.currentTime);
				osc.frequency.exponentialRampToValueAtTime(1200, this.ctx.currentTime + 0.6);
				gain.gain.setValueAtTime(0.1, this.ctx.currentTime);
				gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + 0.6);
				osc.connect(gain);
				gain.connect(this.ctx.destination);
				osc.start();
				osc.stop(this.ctx.currentTime + 0.6);
			},
			playBonus: function () {
				if (!sfxEnabled) { return; }
				this.init();
				if (!this.ctx) { return; }
				var ctx = this.ctx;
				var now = ctx.currentTime;
				[523.25, 659.25, 783.99].forEach(function (freq) {
					var osc = ctx.createOscillator();
					var gain = ctx.createGain();
					osc.type = 'square';
					osc.frequency.setValueAtTime(freq, now);
					gain.gain.setValueAtTime(0.05, now);
					gain.gain.exponentialRampToValueAtTime(0.001, now + 0.3);
					osc.connect(gain);
					gain.connect(ctx.destination);
					osc.start();
					osc.stop(now + 0.3);
				});
			},
			playDeath: function () {
				if (!sfxEnabled) { return; }
				this.init();
				if (!this.ctx) { return; }
				var osc = this.ctx.createOscillator();
				var gain = this.ctx.createGain();
				osc.type = 'sawtooth';
				osc.frequency.setValueAtTime(600, this.ctx.currentTime);
				osc.frequency.linearRampToValueAtTime(40, this.ctx.currentTime + 0.8);
				gain.gain.setValueAtTime(0.2, this.ctx.currentTime);
				gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 0.8);
				osc.connect(gain);
				gain.connect(this.ctx.destination);
				osc.start();
				osc.stop(this.ctx.currentTime + 0.8);
			},
			playBotShatter: function () {
				if (!sfxEnabled) { return; }
				this.init();
				if (!this.ctx) { return; }
				var osc = this.ctx.createOscillator();
				var gain = this.ctx.createGain();
				osc.type = 'sawtooth';
				osc.frequency.setValueAtTime(800, this.ctx.currentTime);
				osc.frequency.setValueAtTime(150, this.ctx.currentTime + 0.05);
				osc.frequency.setValueAtTime(900, this.ctx.currentTime + 0.1);
				gain.gain.setValueAtTime(0.2, this.ctx.currentTime);
				gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 0.2);
				osc.connect(gain);
				gain.connect(this.ctx.destination);
				osc.start();
				osc.stop(this.ctx.currentTime + 0.2);
			}
		};

		var bgmNormal = null;
		var bgmPowerUp = null;
		var bgmCutscene = null;

		function getAudioSource(baseName) {
			var testAudio = new Audio();
			var canOgg = testAudio.canPlayType && testAudio.canPlayType('audio/ogg; codecs="vorbis"').replace(/no/, '');
			return assetBase + baseName + (canOgg ? '.ogg' : '.mp3');
		}

		function initMusic() {
			if (!bgmNormal) {
				bgmNormal = new Audio(getAudioSource('bgm-normal'));
				bgmNormal.loop = true;
				bgmNormal.volume = 0.35;
			}
			if (!bgmPowerUp) {
				bgmPowerUp = new Audio(getAudioSource('bgm-powerup'));
				bgmPowerUp.loop = true;
				bgmPowerUp.volume = 0.45;
			}
			if (!bgmCutscene) {
				bgmCutscene = new Audio(getAudioSource('bgm-cutscene'));
				bgmCutscene.loop = true;
				bgmCutscene.volume = 0.40;
			}
		}

		function startNormalModeMusic() {
			if (!musicEnabled) { return; }
			initMusic();
			if (bgmPowerUp) {
				bgmPowerUp.pause();
				bgmPowerUp.currentTime = 0;
			}
			if (bgmCutscene) {
				bgmCutscene.pause();
				bgmCutscene.currentTime = 0;
			}
			if (bgmNormal) {
				bgmNormal.play().catch(function () {});
			}
		}

		function startPowerUpModeMusic() {
			if (!musicEnabled) { return; }
			initMusic();
			if (bgmNormal) {
				bgmNormal.pause();
				bgmNormal.currentTime = 0;
			}
			if (bgmCutscene) {
				bgmCutscene.pause();
				bgmCutscene.currentTime = 0;
			}
			if (bgmPowerUp) {
				bgmPowerUp.play().catch(function () {});
			}
		}

		function startCutsceneMusic() {
			if (!musicEnabled) { return; }
			initMusic();
			if (bgmNormal) {
				bgmNormal.pause();
				bgmNormal.currentTime = 0;
			}
			if (bgmPowerUp) {
				bgmPowerUp.pause();
				bgmPowerUp.currentTime = 0;
			}
			if (bgmCutscene) {
				bgmCutscene.currentTime = 0;
				bgmCutscene.play().catch(function () {});
			}
		}

		function stopCutsceneMusic() {
			if (bgmCutscene) {
				bgmCutscene.pause();
				bgmCutscene.currentTime = 0;
			}
		}

		function pauseMusic() {
			if (bgmNormal) { bgmNormal.pause(); }
			if (bgmPowerUp) { bgmPowerUp.pause(); }
			if (bgmCutscene) { bgmCutscene.pause(); }
		}

		function stopMusic() {
			if (bgmNormal) {
				bgmNormal.pause();
				bgmNormal.currentTime = 0;
			}
			if (bgmPowerUp) {
				bgmPowerUp.pause();
				bgmPowerUp.currentTime = 0;
			}
			if (bgmCutscene) {
				bgmCutscene.pause();
				bgmCutscene.currentTime = 0;
			}
		}

		function syncMusicState() {
			if (!musicEnabled) {
				pauseMusic();
				return;
			}
			if (inCutscene) {
				startCutsceneMusic();
				return;
			}
			if (playing && !run.over && !run.complete && run.recovery === 0 && !transition) {
				if (fast) {
					startPowerUpModeMusic();
				} else {
					startNormalModeMusic();
				}
			} else {
				pauseMusic();
			}
		}

		function unlockAudio() {
			if (audioUnlocked) { return; }
			audioUnlocked = true;
			CyberSoundFX.init();
			initMusic();
		}
		function element(id) {
			if (id === 'score') {
				return document.getElementById('asc-fm-high-score') || document.getElementById('asc-fm-score') || document.getElementById('high-score') || document.getElementById('score');
			}
			return document.getElementById('asc-fm-' + id) || document.getElementById(id);
		}
		function storageFailure() {
			storageAvailable = false;
			var statusEl = element('storage-status') || element('run-status');
			if (statusEl) {
				statusEl.textContent = 'High scores are unavailable in this browser.';
			}
		}
		function renderScores() {
			var scoreEl = element('score') || element('high-score');
			if (scoreEl) {
				scoreEl.textContent = String(highScore).padStart(6, '0');
			}
		}
		function saveHighScore() {
			if (!storageAvailable || run.score <= highScore) { return; }
			highScore = run.score;
			try { localStorage.setItem(key, String(highScore)); renderScores(); }
			catch (error) { storageFailure(); }
		}
		function imageSource() {
			if (fast) { return assetBase + '128-fast-man.png'; }
			return assetBase + '128-creative-man.png';
		}
		function styleWall(cell, rows, x, y) {
			var top = rows[y - 1]?.[x] === '#';
			var right = rows[y][x + 1] === '#';
			var bottom = rows[y + 1]?.[x] === '#';
			var left = rows[y][x - 1] === '#';
			[top, right, bottom, left].forEach(function (joined, index) {
				var depth = '7px';
				if (joined) { depth = '0px'; }
				cell.style.setProperty('--wall-' + ['top', 'right', 'bottom', 'left'][index], depth);
				cell.style.setProperty('--asc-fm-wall-' + ['top', 'right', 'bottom', 'left'][index], depth);
			});
			var corners = [[top, left], [top, right], [bottom, right], [bottom, left]].map(function (sides) {
				if (!sides[0] && !sides[1]) { return '8px'; }
				return '0';
			});
			cell.style.borderRadius = corners.join(' ');
			var innerCorners = [
				['tl', top, left, rows[y - 1]?.[x - 1]],
				['tr', top, right, rows[y - 1]?.[x + 1]],
				['br', bottom, right, rows[y + 1]?.[x + 1]],
				['bl', bottom, left, rows[y + 1]?.[x - 1]]
			];
			innerCorners.forEach(function (corner) {
				var depth = '0px';
				if (corner[1] && corner[2] && corner[3] !== '#') { depth = '7px'; }
				cell.style.setProperty('--wall-inner-' + corner[0], depth);
				cell.style.setProperty('--asc-fm-wall-inner-' + corner[0], depth);
			});
		}
		function renderMaze() {
			var next = 'desktop';
			if (window.innerWidth < 700 || window.matchMedia('(orientation: portrait) and (pointer: coarse) and (max-width: 900px)').matches) { next = 'phone'; }
			if (next === mazeType) { return; }
			var changed = mazeType !== '';
			mazeType = next;
			clearTransition();
			playing = false;
			started = false;
			lastFrame = null;
			cells = [];
			tunnelCopies.clear();
			var round = 1;
			if (roundCarry) { round = roundCarry.round; }
			var rows = window.fastManMapForRound(mazeType, round);
			run = new window.FastManRun(rows, mazeType, round, document.querySelector('input[name="difficulty"]:checked').value);
			if (roundCarry) {
				run.score = roundCarry.score;
				run.lives = roundCarry.lives;
				run.nextLifeScore = 20000;
				if (run.score >= 20000) { run.nextLifeScore = 20000 + (Math.floor((run.score - 20000) / 40000) + 1) * 40000; }
				roundCarry = null;
			}
			element('game-over').hidden = true;
			element('death-message').hidden = true;
			hideTimedMessage();
			var board = element('maze');
			board.replaceChildren();
			board.classList.toggle('asc-fm-phone', mazeType === 'phone');
			board.classList.toggle('phone', mazeType === 'phone');
			board.dataset.mapTheme = window.fastManMapThemeForRound(round);
			board.style.gridTemplateColumns = 'repeat(' + rows[0].length + ', 1fr)';
			board.style.gridTemplateRows = 'repeat(' + rows.length + ', 1fr)';
			var agent = 0;
			rows.forEach(function (row, y) {
				if (mazeType === 'phone' && (y === 0 || y === rows.length - 1)) {
					var reserved = document.createElement('div');
					reserved.className = 'reserved asc-fm-reserved';
					reserved.id = 'phone-score';
					reserved.textContent = 'Score 000000';
					if (y > 0) { reserved.id = 'phone-hint'; reserved.textContent = 'Swipe here to change direction'; }
					board.append(reserved);
					return;
				}
				Array.from(row).forEach(function (symbol, x) {
					var cell = document.createElement('div');
					cell.className = 'cell asc-fm-cell';
					cell.dataset.x = String(x);
					cell.dataset.y = String(y);
					cells[y * rows[0].length + x] = cell;
					switch (symbol) {
						case '#':
							cell.classList.add('wall', 'asc-fm-wall');
							styleWall(cell, rows, x, y);
							break;
						case '.': cell.classList.add('pellet', 'asc-fm-pellet'); break;
						case 'o':
						case 'P':
							var image = document.createElement('img');
							image.src = assetBase + 'power-pill.svg'; image.className = 'pill asc-fm-pill'; image.alt = 'aS.c power pill';
							if (symbol === 'P') { image.src = imageSource(); image.className = 'fastman asc-fm-fastman'; image.id = 'fastman'; image.alt = 'Fast-Man'; }
							if (symbol === 'o') { cell.classList.add('pill', 'asc-fm-pill'); cell.append(image); }
							else { board.append(image); }
							break;
						case 'C':
							var bot = run.agents[agent++];
							if (!bot) { break; }
							var botName = bot.name;
							var shape = document.createElement('img');
							shape.className = 'agent asc-fm-agent bot-' + bot.color + ' asc-fm-bot-' + bot.color; shape.title = botName + ' bot'; shape.id = 'agent-' + (agent - 1);
							shape.src = botImages[botName] || botImageSource(botName);
							shape.alt = botName + ' bot';
							board.append(shape); break;
					}
					board.append(cell);
				});
			});
			board.setAttribute('aria-label', 'Maze. Use arrow keys or WASD to move.');
			positionPlayer(true);
			positionAgents(true);
			setMode(false);
			updateRun();
			var message = 'Choose a direction to start. Avoid blue, purple, and green bots; tag orange bots.';
			if (changed) { message = 'Maze changed. A fresh run is ready.'; }
			element('run-status').textContent = message;
		}
		function setMode(enabled) {
			fast = enabled;
			document.body.classList.toggle('asc-fm-fast-mode', fast);
			document.body.classList.toggle('fast-mode', fast);
			var title = 'Creative Mode';
			var badge = 'CREATIVE MODE';

			if (fast) { title = 'Fast Mode'; badge = 'FAST MODE'; }
			element('fastman').src = imageSource();
			var rewardEl = element('reward');
			if (rewardEl) {
				rewardEl.src = imageSource();
				rewardEl.alt = title + ' visual source for Fast-Man';
			}
			var rewardBadgeEl = element('reward-badge');
			if (rewardBadgeEl) {
				rewardBadgeEl.textContent = badge;
			}
			syncMusicState();
		}
		function placeSprite(sprite, position, inset) {
			var rawX = position.x;
			var x = (rawX % run.width + run.width) % run.width;
			sprite.style.left = (x + inset) * 100 / run.width + '%';
			sprite.style.top = (position.y + inset) * 100 / run.height + '%';
			var copy = tunnelCopies.get(sprite);
			if (rawX < 0 || rawX > run.width - 1) {
				if (!copy) {
					copy = sprite.cloneNode(true);
					copy.removeAttribute('id');
					copy.removeAttribute('title');
					copy.setAttribute('aria-hidden', 'true');
					sprite.parentNode.append(copy);
					tunnelCopies.set(sprite, copy);
				}
				copy.className = sprite.className + ' tunnel-copy asc-fm-tunnel-copy';
				copy.style.cssText = sprite.style.cssText;
				copy.style.left = (rawX + inset) * 100 / run.width + '%';
				if (sprite.tagName === 'IMG') { copy.src = sprite.src; }
				copy.hidden = false;
			} else if (copy) { copy.hidden = true; }
		}
		function positionPlayer(instant) {
			var player = element('fastman');
			if (!player) { return; }
			var facing = { right: ['0deg', -1], down: ['-90deg', 1], left: ['0deg', 1], up: ['90deg', 1] };
			if (run.direction && facing[run.direction]) {
				player.style.setProperty('--facing', facing[run.direction][0]);
				player.style.setProperty('--asc-fm-facing', facing[run.direction][0]);
				player.style.setProperty('--flip', facing[run.direction][1]);
				player.style.setProperty('--asc-fm-flip', facing[run.direction][1]);
			}
			player.classList.toggle('asc-fm-is-moving', playing && !run.over && run.recovery === 0);
			player.classList.toggle('is-moving', playing && !run.over && run.recovery === 0);
			player.style.width = 100 / run.width + '%';
			player.style.height = 100 / run.height + '%';
			var position = { x: run.x, y: run.y };
			if (!instant) { position = run.motionPosition(run, run.playerMotion); }
			placeSprite(player, position, 0);
		}
		function renderBonus(bonus) {
			var old = element('bonus-item');
			if (old) { old.remove(); }
			if (!bonus) { return; }
			var logos = { WordPress: 'wordpress-logo.svg', Gemini: 'gemini-logo.svg', ChatGPT: 'chatgpt-logo.svg', Nginx: 'nginx-logo.svg', AWS: 'aws-logo.svg' };
			var item = document.createElement('img');
			item.id = 'bonus-item';
			item.className = 'bonus-item asc-fm-bonus-item';
			item.src = assetBase + logos[bonus.name];
			item.alt = bonus.name + ' bonus';
			item.title = bonus.name + ' bonus, ' + bonus.points + ' points';
			item.style.width = 80 / run.width + '%';
			item.style.height = 80 / run.height + '%';
			item.style.left = (bonus.x + 0.1) * 100 / run.width + '%';
			item.style.top = (bonus.y + 0.1) * 100 / run.height + '%';
			element('maze').append(item);
		}
		function showPoints(points, x, y) {
			var popup = document.createElement('span');
			popup.className = 'points-popup asc-fm-points-popup';
			popup.textContent = '+' + points;
			popup.style.left = (x + 0.2) * 100 / run.width + '%';
			popup.style.top = y * 100 / run.height + '%';
			element('maze').append(popup);
			window.setTimeout(function () { popup.remove(); }, 850);
		}
		function hideTimedMessage() {
			if (timedMessageTimer) { window.clearTimeout(timedMessageTimer); timedMessageTimer = null; }
			var message = element('bonus-life-message');
			message.classList.remove('is-persistent', 'asc-fm-is-persistent');
			message.hidden = true;
		}
		function showTimedMessage(text, duration) {
			if (transition) { return; }
			var message = element('bonus-life-message');
			if (timedMessageTimer) { window.clearTimeout(timedMessageTimer); timedMessageTimer = null; }
			message.textContent = text;
			message.classList.toggle('is-persistent', duration === 0);
			message.classList.toggle('asc-fm-is-persistent', duration === 0);
			message.hidden = true;
			void message.offsetWidth;
			message.hidden = false;
			if (duration !== 0) { timedMessageTimer = window.setTimeout(function () { message.hidden = true; timedMessageTimer = null; }, duration || messageDuration); }
		}
		function showRewardMessage() {
			showTimedMessage(rewardMessage);
		}
		function showExtraWebsite(delay) {
			if (delay) { window.setTimeout(function () { showTimedMessage('Extra reboot earned!'); }, delay); return; }
			showTimedMessage('Extra reboot earned!');
		}
		function clearCutscene() {
			if (cutsceneTimer) {
				window.clearTimeout(cutsceneTimer);
				cutsceneTimer = null;
			}
			if (cutsceneFadeTimer) {
				window.clearTimeout(cutsceneFadeTimer);
				cutsceneFadeTimer = null;
			}
			stopCutsceneMusic();
			var cs = element('cutscene');
			if (cs) {
				cs.classList.remove('asc-fm-cutscene-active', 'asc-fm-cutscene-fading');
				cs.hidden = true;
			}
			inCutscene = false;
			cutsceneCallback = null;
		}
		function skipCutscene() {
			if (!inCutscene) { return; }
			var cb = cutsceneCallback;
			clearCutscene();
			if (cb) { cb(); }
		}
		function playCutscene(onComplete) {
			clearCutscene();
			clearTransition();
			pauseMusic();
			playing = false;
			lastFrame = null;
			gesture = null;
			inCutscene = true;
			cutsceneCallback = onComplete;
			var cs = element('cutscene');
			if (!cs) {
				inCutscene = false;
				if (onComplete) { onComplete(); }
				return;
			}
			startCutsceneMusic();
			cs.hidden = false;
			void cs.offsetWidth;
			cs.classList.add('asc-fm-cutscene-active');
			element('run-status').textContent = 'Intermission!';
			updateRun();

			cutsceneTimer = window.setTimeout(function () {
				cs.classList.add('asc-fm-cutscene-fading');
				cutsceneFadeTimer = window.setTimeout(function () {
					var cb = cutsceneCallback;
					clearCutscene();
					if (cb) { cb(); }
				}, 600);
			}, 24500);
		}
		function clearTransition() {
			if (inCutscene) { clearCutscene(); }
			window.clearTimeout(transitionTimer);
			transitionTimer = null;
			transition = null;
			transitionReady = false;
			element('game-over').hidden = true;
			element('maze').classList.remove('board-flash', 'asc-fm-board-flash');
		}
		function showTransition(kind, text) {
			clearTransition();
			hideTimedMessage();
			pauseMusic();
			transition = kind;
			playing = false;
			lastFrame = null;
			gesture = null;
			element('game-over-title').textContent = text;
			element('game-over-restart').textContent = kind === 'round' ? 'Next round' : kind === 'website' ? 'Reboot' : 'New game';
			element('game-over-restart').disabled = true;
			element('game-over').hidden = false;
			void element('maze').offsetWidth;
			element('maze').classList.add('board-flash', 'asc-fm-board-flash');
			transitionTimer = window.setTimeout(function () {
				transitionReady = true;
				element('game-over-restart').disabled = false;
				element('game-over-restart').focus({ preventScroll: true });
				element('maze').classList.remove('board-flash', 'asc-fm-board-flash');
				updateRun();
			}, transitionDuration);
		}
		function continueTransition(direction) {
			if (!transitionReady) { return; }
			var kind = transition;
			clearTransition();
			if (kind === 'round') { startNextRound(direction); return; }
			if (kind === 'over') { newRun(); return; }
			run.recovery = 0;
			lastFrame = null;
			if (direction) { chooseDirection(direction); }
			else { playing = true; updateRun(); element('run-status').textContent = 'Choose a direction to move.'; }
		}
		function hideDeathMessage() {
			element('death-message').hidden = true;
		}
		function positionAgents(instant) {
			run.agents.forEach(function (agent, index) {
				var shape = element('agent-' + index);
				if (!shape) { return; }
				shape.style.width = 90 / run.width + '%';
				shape.style.height = 90 / run.height + '%';
				var position = { x: agent.x, y: agent.y };
				if (!instant && agent.wait === 0) { position = run.motionPosition(agent, agent.motion); }
				placeSprite(shape, position, 0.05);
				shape.style.opacity = '1';
				if (agent.wait > 0) { shape.style.opacity = '.3'; }
				shape.classList.toggle('asc-fm-is-vulnerable', agent.vulnerable);
				shape.classList.toggle('is-vulnerable', agent.vulnerable);
			});
		}
		function updateRun() {
			element('run-score').textContent = String(run.score).padStart(6, '0');
			element('remaining').textContent = run.remaining;
			element('lives').textContent = run.lives;
			element('round').textContent = run.round;
			element('start').disabled = inCutscene || playing || started || run.complete || run.over;
			element('pause').disabled = inCutscene || !!transition || !started || run.complete || run.over;
			element('next-round').disabled = inCutscene || !run.complete || (transition && !transitionReady);
			element('restart').disabled = inCutscene || (!!transition && !transitionReady);
			element('pause').textContent = playing ? 'Pause' : 'Resume';
			if (element('phone-score')) { element('phone-score').textContent = 'Score ' + run.score + ' · Reboots ' + run.lives + ' · Round ' + run.round; }
			var seconds = Math.ceil(run.power / 1000);
			var warning = run.power > 0 && run.power <= run.warningDuration;
			element('maze').classList.toggle('asc-fm-power-warning', warning);
			element('maze').classList.toggle('power-warning', warning);
			element('power-time').textContent = 'Creative Mode';
			if (run.power > 0) { element('power-time').textContent = 'Fast Mode: ' + seconds + 's'; }
			if (warning) { element('power-time').textContent += ' · Ending soon'; }
			if (transition) { element('run-status').textContent = transitionReady ? 'Choose a direction or use the button above to continue.' : 'Please wait a moment before continuing.'; }
		}
		function playHitAnimation() {
			var player = element('fastman');
			player.classList.remove('is-hit', 'asc-fm-is-hit');
			void player.offsetWidth;
			player.classList.add('is-hit', 'asc-fm-is-hit');
			player.addEventListener('animationend', function () { player.classList.remove('is-hit', 'asc-fm-is-hit'); }, { once: true });
			window.setTimeout(function () { player.classList.remove('is-hit', 'asc-fm-is-hit'); }, 650);
		}
		function pause() {
			playing = false;
			lastFrame = null;
			pauseMusic();
			element('fastman').classList.remove('is-moving', 'asc-fm-is-moving');
			updateRun();
			if (!run.complete && !run.over) { element('run-status').textContent = 'Paused. Choose a direction or resume to continue.'; }
		}
		function chooseDirection(direction) {
			if (inCutscene) { return; }
			if (transition) { if (transition !== 'over') { continueTransition(direction); } return; }
			if (run.over) { return; }
			var firstDirection = Array.isArray(direction) ? direction[0] : direction;
			if (run.complete) { startNextRound(firstDirection); return; }
			run.queue(direction);
			if (!started) { lastFrame = null; }
			started = true;
			playing = true;
			var fastmanEl = element('fastman');
			if (fastmanEl) { fastmanEl.classList.add('is-moving', 'asc-fm-is-moving'); }
			element('run-status').textContent = 'Collect dots. Tag orange bots in Fast Mode. Avoid blue, purple, and green bots.';
			updateRun();
			syncMusicState();
		}
		function startNextRound(direction) {
			roundCarry = { round: run.round + 1, score: run.score, lives: run.lives };
			mazeType = '';
			renderMaze();
			if (!direction) { pauseMusic(); element('run-status').textContent = 'Round ' + run.round + '. Choose a direction to start.'; updateRun(); return; }
			run.queue(direction);
			started = true;
			playing = true;
			var fastmanEl = element('fastman');
			if (fastmanEl) { fastmanEl.classList.add('is-moving', 'asc-fm-is-moving'); }
			element('run-status').textContent = 'Round ' + run.round + '. Keep moving!';
			updateRun();
			syncMusicState();
		}
		function tick(timestamp) {
			if (playing && lastFrame !== null) {
				var elapsed = Math.min(timestamp - lastFrame, 250);
				var priorPower = run.power;
				var priorRecovery = run.recovery;
				var events = [];
				var bonusCollected = false;
				while (elapsed > 0) {
					var step = Math.min(25, elapsed);
					events.push.apply(events, run.advance(step));
					elapsed -= step;
					if (run.complete || run.over || run.recovery > 0) { break; }
				}
				events.forEach(function (event) {
					if (event.type === 'move') {
						positionPlayer(false);
						hideDeathMessage();
					}
					if (event.type === 'collect' && event.move) {
						var move = event.move;
						var cell = cells[move.y * run.width + move.x];
						if (!cell) {
							var board = element('maze');
							if (board) { cell = board.querySelector('.asc-fm-cell[data-x="' + move.x + '"][data-y="' + move.y + '"]'); }
						}
						if (move.collected === '.') {
							if (cell) {
								cell.classList.remove('pellet', 'asc-fm-pellet');
								cell.replaceChildren();
							}
							CyberSoundFX.playWaka();
						}
						if (move.collected === 'o') {
							if (cell) {
								cell.classList.remove('pill', 'asc-fm-pill');
								cell.replaceChildren();
							}
							element('run-status').textContent = 'Fast Mode: ' + Math.ceil(run.powerDuration / 1000) + ' seconds. Chase the orange bots!';
							CyberSoundFX.playPowerUp();
						}
					}
					if (event.type === 'tag') {
						showPoints(event.points, event.x, event.y);
						showTimedMessage(tagMessage);
						CyberSoundFX.playBotShatter();
					}
					if (event.type === 'bonus-spawn') { renderBonus(event.bonus); element('run-status').textContent = event.bonus.name + ' bonus appeared!'; }
					if (event.type === 'bonus') {
						renderBonus(null);
						showPoints(event.bonus.points, event.bonus.x, event.bonus.y);
						showRewardMessage();
						bonusCollected = true;
						element('run-status').textContent = event.bonus.name + ' bonus: ' + event.bonus.points + ' points!';
						CyberSoundFX.playBonus();
					}
					if (event.lives) { showExtraWebsite(bonusCollected ? messageDuration + 50 : 0); element('run-status').textContent = 'Extra reboot earned!'; }
					if (event.type === 'life') {
						positionPlayer(true);
						positionAgents(true);
						playHitAnimation();
						showTransition('website', deathMessage);
						CyberSoundFX.playDeath();
						pauseMusic();
					}
					if (event.type === 'clear') {
						positionPlayer(true);
						saveHighScore();
						pauseMusic();
						if (run.round === 2) {
							playCutscene(function () {
								startNextRound(null);
							});
						} else {
							showTransition('round', completeMessages[completeMessageIndex]);
							completeMessageIndex = (completeMessageIndex + 1) % completeMessages.length;
						}
					}
					if (event.type === 'over') {
						positionPlayer(true);
						positionAgents(true);
						playHitAnimation();
						saveHighScore();
						showTransition('over', 'Game Over!');
						CyberSoundFX.playDeath();
						stopMusic();
					}
				});
				if (priorRecovery > 0 && run.recovery === 0) { element('run-status').textContent = 'Go! Choose a direction to move.'; }
				if (priorPower > run.warningDuration && run.power > 0 && run.power <= run.warningDuration) { element('run-status').textContent = 'Fast Mode ends in ' + Math.ceil(run.warningDuration / 1000) + ' seconds. The orange bots are flashing.'; }
				if (priorPower > 0 && run.power === 0 && !run.over && !run.complete && run.recovery === 0) { element('run-status').textContent = 'Fast Mode ended. Avoid the blue, purple, and green bots.'; }
				if (fast !== (run.power > 0)) { setMode(run.power > 0); }
				updateRun();
			}
			var moving = playing && run.playerMotion !== null && !run.over && run.recovery === 0;
			var fastmanEl = element('fastman');
			if (fastmanEl) {
				fastmanEl.classList.toggle('is-moving', moving);
				fastmanEl.classList.toggle('asc-fm-is-moving', moving);
			}
			positionPlayer(false);
			positionAgents(false);
			lastFrame = timestamp;
			window.requestAnimationFrame(tick);
		}
		element('start').addEventListener('click', function () {
			if (started || run.over || run.complete) { return; }
			started = true;
			playing = true;
			lastFrame = null;
			var fastmanEl = element('fastman');
			if (fastmanEl) { fastmanEl.classList.add('is-moving', 'asc-fm-is-moving'); }
			element('run-status').textContent = 'Choose a direction. Use arrow keys, WASD, or swipe the maze.';
			updateRun();
			syncMusicState();
			element('maze').focus({ preventScroll: true });
		});
		element('pause').addEventListener('click', function () {
			if (transition) { return; }
			if (playing) { pause(); return; }
			if (!started || run.over || run.complete) { return; }
			playing = true; lastFrame = null;
			var fastmanEl = element('fastman');
			if (fastmanEl) { fastmanEl.classList.add('is-moving', 'asc-fm-is-moving'); }
			element('run-status').textContent = 'Choose a direction. Use arrow keys, WASD, or swipe the maze.';
			updateRun();
			syncMusicState();
			element('maze').focus({ preventScroll: true });
		});
		function newRun() { if (transition && !transitionReady) { return; } stopMusic(); roundCarry = null; mazeType = ''; renderMaze(); }
		element('restart').addEventListener('click', newRun);
		element('next-round').addEventListener('click', function () { if (run.complete) { continueTransition(null); } });
		element('game-over-restart').addEventListener('click', function () { continueTransition(null); });
		var keys = { ArrowUp: 'up', ArrowRight: 'right', ArrowDown: 'down', ArrowLeft: 'left', w: 'up', d: 'right', s: 'down', a: 'left' };
		document.querySelectorAll('input[name="difficulty"]').forEach(function (input) {
			input.addEventListener('change', function () { run.setDifficulty(input.value); });
		});
		document.addEventListener('keydown', function (event) {
			if (event.altKey || event.ctrlKey || event.metaKey) { return; }
			if (inCutscene) {
				if (event.code === 'Space' || event.key === 'Enter' || event.key === 'Escape') {
					event.preventDefault();
					skipCutscene();
				}
				return;
			}
			var target = event.target;
			if (target && typeof target.closest === 'function') {
				if (target.closest('input, textarea, select, [contenteditable="true"]')) { return; }
				if (target.closest('button') && (event.key === 'Enter' || event.code === 'Space')) { return; }
			}
			var keyName = event.key;
			if (keyName.length === 1) { keyName = keyName.toLowerCase(); }
			if (Object.hasOwn(keys, keyName)) { event.preventDefault(); if (!transition || !event.repeat) { chooseDirection(keys[keyName]); } }
			if ((keyName === 'p' || event.code === 'Space') && !event.repeat && !run.complete && !run.over) { event.preventDefault(); element('pause').click(); }
		});
		var gesture = null;
		element('maze').addEventListener('pointerdown', function (event) {
			if (!event.isPrimary || event.button !== 0) { return; }
			gesture = { x: event.clientX, y: event.clientY, id: event.pointerId };
			this.setPointerCapture(event.pointerId);
		});
		element('maze').addEventListener('pointermove', function (event) {
			if (!gesture || gesture.id !== event.pointerId) { return; }
			var dx = event.clientX - gesture.x;
			var dy = event.clientY - gesture.y;
			if (Math.max(Math.abs(dx), Math.abs(dy)) < 18) { return; }
			var direction = 'up';
			if (Math.abs(dx) > Math.abs(dy)) { direction = 'left'; if (dx > 0) { direction = 'right'; } }
			else if (dy > 0) { direction = 'down'; }
			chooseDirection(direction);
			gesture.x = event.clientX; gesture.y = event.clientY;
		});
		['pointerup', 'pointercancel', 'lostpointercapture'].forEach(function (name) {
			element('maze').addEventListener(name, function () { gesture = null; });
		});
		window.addEventListener('blur', pause);
		document.addEventListener('visibilitychange', function () { if (document.hidden) { pause(); } });
		try {
			var raw = localStorage.getItem(key);
			if (raw !== null && /^\d+$/.test(raw)) { highScore = Number(raw); }
		} catch (error) { storageFailure(); }
		window.addEventListener('resize', renderMaze);
		document.querySelectorAll('a[href="#asc-fm-stage"], a[href="#asc-fm-board"]').forEach(function (link) {
			link.addEventListener('click', function (event) {
				var target = document.getElementById('asc-fm-board') || document.getElementById('asc-fm-stage');
				if (target) {
					event.preventDefault();
					var targetTop = target.getBoundingClientRect().top + window.pageYOffset;
					window.scrollTo({
						top: targetTop,
						behavior: 'smooth'
					});
					if (window.history && window.history.pushState) { window.history.pushState(null, '', link.getAttribute('href')); }
				}
			});
		});
		var skipBtn = element('cutscene-skip');
		if (skipBtn) {
			skipBtn.addEventListener('click', function (e) {
				e.stopPropagation();
				skipCutscene();
			});
		}
		var cutsceneEl = element('cutscene');
		if (cutsceneEl) {
			cutsceneEl.addEventListener('click', function () {
				skipCutscene();
			});
		}
		window.fastManPlayCutscene = function () {
			playCutscene(function () {
				startNextRound(null);
			});
		};
		var sfxToggle = element('toggle-sfx');
		if (sfxToggle) {
			sfxToggle.checked = sfxEnabled;
			sfxToggle.addEventListener('change', function () {
				sfxEnabled = this.checked;
				if (sfxEnabled) {
					CyberSoundFX.init();
				}
			});
		}
		var musicToggle = element('toggle-music');
		if (musicToggle) {
			musicToggle.checked = musicEnabled;
			musicToggle.addEventListener('change', function () {
				musicEnabled = this.checked;
				if (musicEnabled) {
					CyberSoundFX.init();
					initMusic();
				}
				syncMusicState();
			});
		}
		document.addEventListener('click', unlockAudio, { once: true });
		document.addEventListener('keydown', unlockAudio, { once: true });
		document.addEventListener('touchstart', unlockAudio, { once: true });
		renderMaze();
		renderScores();
		window.requestAnimationFrame(tick);
	}());

}

# 🧍 VZNPC

**A lightweight NPC and floating text plugin for [VeoZaxAPI](https://github.com/VeoZaxOfficial/Multiversion-API-0.14.x-0.15.10-1.1.x-VeoZax-API-Build-3.0).**

Place human NPCs and floating text (holograms) anywhere in your worlds with `/vznpc`. Both look right on every supported client version, so old and new players see the same thing. Works as a `.phar` or as a plain source folder.

> Made by **VeoZax** for VeoZaxAPI - the legacy-friendly PocketMine-MP API.

---

## ✨ Features

- **Human NPCs** - copy your skin, armor and held item onto an NPC
- **Floating text** - multi-line, coloured holograms
  - players on **0.14.x - 0.15.10** and **1.1.x and above** both see real floating text
- **Live placeholders** - online players, TPS, date, time and more refresh on their own about once a second
- **Tap commands** - run commands when a player taps an NPC
- **Multi-world** - show copies of one NPC in several worlds, or move it between worlds
- **World-specific counts** - make `{c}` count only the players in one world
- **Easy removal** - by ID, by hitting the NPC, or instantly with an emerald (operators only)
- **Clear messages** - colour-coded replies that say what happened and what to do next
- Zero dependencies, no database - NPCs are saved with your worlds

---

## 📋 Requirements

- A server running with **VeoZaxAPI**

---

## 📥 Installation

<p align="center">
  <a href="https://youtu.be/6ToOYE3tCy0">
    <img src="https://img.youtube.com/vi/6ToOYE3tCy0/maxresdefault.jpg" alt="VZNPC installation tutorial" width="560"/>
  </a>
  <br/>
  <b>▶ Watch the installation tutorial</b>
</p>

### As a `.phar`
1. Download the `VZNPC` plugin from the [releases](https://github.com/VeoZaxOfficial/VZNPC-VeoZaxAPI/releases) or as a Zip from the repo.
2. Drop it into your server's `plugins/` folder.
3. Restart the server.

### As a source folder
1. Download this repository as zip.
2. Put the `VZNPC` folder into your server's `plugins/` folder.
   The folder must contain `plugin.yml` and a `src/` directory directly inside it.
3. Open `veozaxapi.yml` in your server's root folder and enable folder loading:
   ```yaml
   developer:
     folder-plugin-loader: true
   ```
4. Restart the server. (A reload is not enough - this setting is read at startup.)

---

## 🚀 Quick start

Stand where you want the NPC and run:

```
/vznpc spawn human Welcome to the server
/vznpc spawn floatingtext {color}6Welcome{line}{color}a{c} {color}7players online
```

The plugin replies with the NPC's **ID**. Use it to edit or remove the NPC:

```
/vznpc edit <id> name {color}bNew name
/vznpc remove <id>
```

---

## 💬 Commands

| Command | Description | Who can use it |
|---|---|---|
| `/vznpc help` | Shows the command guide | Operators |
| `/vznpc spawn <type> [world] [name]` | Creates an NPC where you stand | Operators |
| `/vznpc edit <id> <option> ...` | Changes an NPC (see below) | Operators |
| `/vznpc id` | Then hit an NPC to see its ID | Operators |
| `/vznpc remove [id]` | Removes an NPC by ID, or alone and then hit the NPC | Operators |
| `/vznpc cancel` | Leaves remove or ID mode | Operators |
| `/vznpc updateall` | Updates older NPCs to the newest data format | Operators |
| `/vznpc version` | Shows the plugin version | Operators |
| `/rca <player> <command>` | Runs a command as another player | Operators |

### Spawn types

| Type | Aliases |
|---|---|
| `human` | `player` |
| `floatingtext` | `hologram`, `text`, `sign` |

The optional `[world]` is the world that `{c}` counts. If the first word after the type is a real world name it is used as that world, otherwise it is part of the name.

### Edit options

| Option | What it does |
|---|---|
| `name <name>` | Changes the displayed name |
| `namevisible <1/0>` | Shows or hides the name |
| `skin` | Copies your skin onto the NPC (humans only) |
| `helmet` / `chestplate` / `leggings` / `boots <id>` | Sets armor (humans only) |
| `hand <id>` | Sets the held item (humans only) |
| `menuname <name/remove>` | Sets or clears the player-list name (humans only) |
| `addcommand <command>` | Adds a command that runs when the NPC is tapped |
| `delcommand <command>` | Removes a tap command |
| `listcommands` | Lists the NPC's tap commands |
| `update` | Converts old tap commands to the new format |
| `tphere` / `tpto` | Teleports the NPC to you, or you to the NPC |
| `world <world>` | Moves the NPC to another world |
| `worlds <w1,w2,.../clear/list>` | Shows the NPC in several worlds, removes the copies, or lists them |
| `trackworld <world/clear>` | Makes `{c}` count only that world |

## 🔑 Permissions

| Permission | Description | Default |
|---|---|---|
| `vznpc` | All VZNPC commands | `op` |
| `vznpc.command` | Use `/vznpc` | `op` |
| `vznpc.edit` | Edit NPC data | `op` |
| `vznpc.remove` | Remove NPCs | `op` |
| `vznpc.id` | View NPC IDs | `op` |
| `vznpc.help` | View the help pages | `op` |
| `vznpc.version` | View the plugin version | `op` |
| `vznpc.rca` | Run commands as other players | `op` |
| `vznpc.seeId` | See the entity ID under NPC names (not recommended) | `false` |

---

## 🏷️ Tags & placeholders

Use these inside NPC names and floating text.

| Tag | Result |
|---|---|
| `{color}` | The colour symbol (`{color}a` = green, `{color}l` = bold) |
| `{line}` | A new line |
| `{c}` / `{o}` / `{online}` | Players online (or in the tracked world) |
| `{max}` / `{p}` / `{maxplayers}` | Maximum players |
| `{tps}` | Server TPS |
| `{date}` / `{time}` | Current date and time |
| `{world}` / `{level}` | The NPC's world |
| `{x}` `{y}` `{z}` | The NPC's position |
| `{name}` `{display_name}` `{nametag}` | The player who is looking at the NPC |

---

## ⚙️ How it works

1. An operator places an NPC with `/vznpc spawn` and gets its **ID**.
2. The NPC can be customised with `/vznpc edit` - name, skin, armor, commands, worlds and more.
3. When a player taps the NPC, its tap commands run.
4. Operators remove it with `/vznpc remove`, or with an emerald.

All messages are built into the plugin and can be edited in `Main.php`.

### Tap commands

Commands added with `addcommand` run **as the console** when a player taps the NPC. Use `{player}` for the name of the player who tapped.

```
/vznpc edit <id> addcommand give {player} 264 1
/vznpc edit <id> addcommand tell {player} Welcome!
```

Taps are limited to one every 0.75 seconds per player to prevent spam.

### 💚 Emerald removal tool

Operators can **hit or right-click any NPC while holding an emerald** to remove it instantly. This also works on floating text.
### 🎨 Message colours

The colour of the `[VZNPC]` prefix tells you what kind of message it is:

| Prefix | Meaning |
|---|---|
| green | Success |
| red | Error |
| aqua | Information |
| gold | Usage / missing input |

---

## 🗂️ Data & configuration

- NPCs are saved together with your world data, so they stay after restarts.
- There is no `config.yml` - the plugin has no options yet.

---

## 📝 Notes

- Only **human** NPCs and **floating text** can be spawned.
- Armor, held item, skin and player-list name only work on humans.

---

## 🤝 Contributing

Issues and pull requests are welcome.

If you're reporting a bug, please include your **client version**, your PocketMine-MP version, what you saw on screen, and relevant server log output.

---

## Credits

Developed by **[VeoZax](https://github.com/VeoZaxOfficial)**

---

## License

Released under the [MIT License](LICENSE). Feel free to use, modify, and redistribute with credit to the author VeoZax.

---

## 🔗 Links

<p align="left">
  <a href="https://info.veozax.xyz/"><img src="https://img.shields.io/badge/Website-000000?style=for-the-badge&logo=googlechrome&logoColor=white" alt="Website"/></a>
  <a href="https://www.youtube.com/channel/UC33-nbRHQn-3H56aQc0sanA"><img src="https://img.shields.io/badge/YouTube-FF0000?style=for-the-badge&logo=youtube&logoColor=white" alt="YouTube"/></a>
  <a href="https://discord.gg/dCzgPYam2J"><img src="https://img.shields.io/badge/Discord-5865F2?style=for-the-badge&logo=discord&logoColor=white" alt="Discord"/></a>
  <a href="https://www.instagram.com/veozax"><img src="https://img.shields.io/badge/Instagram-E4405F?style=for-the-badge&logo=instagram&logoColor=white" alt="Instagram"/></a>
  <a href="https://www.tiktok.com/@veozaxofficial"><img src="https://img.shields.io/badge/TikTok-000000?style=for-the-badge&logo=tiktok&logoColor=white" alt="TikTok"/></a>
  <a href="https://www.facebook.com/VeoZax/"><img src="https://img.shields.io/badge/Facebook-1877F2?style=for-the-badge&logo=facebook&logoColor=white" alt="Facebook"/></a>
  <a href="https://github.com/VeoZaxOfficial"><img src="https://img.shields.io/badge/GitHub-181717?style=for-the-badge&logo=github&logoColor=white" alt="GitHub"/></a>
</p>

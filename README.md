<div align="center">
<h1>AntiBugPing</h1>
<p><strong>Advanced network heuristic and desync mitigation engine for PocketMine-MP 5.</strong></p>

<img src="https://img.shields.io/badge/PocketMine--MP-5.0+-000000?style=for-the-badge&logo=php&logoColor=white" alt="PMMP 5">
<img src="https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP Version">
</div>

<br>

## ⯈ Core Functionality

AntiBugPing implements real-time connection profiling to detect and mitigate lag-switch style abuse, artificial desynchronization, and erratic network behavior.

- **Heuristic Profiling:** Continuously tracks median ping, connection jitter, and packet transmission silence on a per-player basis.
- **Dynamic Risk Scoring:** Translates network anomalies into a short-lived risk score, utilizing an automatic heuristic decay algorithm to prevent false positives during brief network spikes.
- **Progressive Mitigation Engine:** Automatically executes escalating penalties based on desync severity:
  - Restricts unauthorized combat and interaction actions.
  - Triggers safe setback teleportation.
  - Applies temporary movement freezing.
  - Disconnects clients experiencing chronic high-risk network states (configurable).
  - Initiates watchdog quarantine for clients failing to broadcast packets within the expected tick threshold.

## ⯈ Architecture & Efficiency

Designed for high-concurrency production environments, ensuring zero impact on the main server thread.

- **Memory Safety:** Leverages `WeakMap` for automated per-player state lifecycle management, entirely eliminating memory leaks and the need for manual garbage collection arrays.
- **Zero-Allocation Buffers:** Utilizes fixed-size, in-memory circular buffers for sampling. Requires zero database I/O.
- **Event-Driven Execution:** Mitigation logic is strictly bound to relevant inbound network packets and critical gameplay events, bypassing arbitrary repetitive tasks.

## ⯈ Configuration Tuning

The system initializes with a `balanced` profile. Administrators must fine-tune `resources/config.yml` according to the infrastructure's routing topology.

| Parameter | Recommended Adjustment |
| :--- | :--- |
| `thresholds.high-ping-ms` | Increase if the server infrastructure is heavily reliant on cross-continental routing. |
| `thresholds.high-jitter-ms` | Increase if the primary player base utilizes cellular or highly volatile mobile connections. |
| `min-ping-samples` | Increase to demand a larger statistical sample size prior to mitigation execution. |
| `thresholds.action-score-cooldown-ticks` | Increase to dampen false-positive risk spikes during dense PvP interactions. |
| `watchdog.quarantine-after-silent-multiplier` | Calibrate to govern the strictness of the silent-connection quarantine trigger. |
| `mitigation.kick-enabled` | Keep set to `false` during initial deployment until network telemetry is validated. |

## ⯈ Access Control

| Node | Description | Default Status |
| :--- | :--- | :--- |
| `antibugping.bypass` | Grants absolute immunity to heuristic checks and mitigation penalties. | `op` |
| `antibugping.notify` | Subscribes the user to real-time administrative telemetry and mitigation alerts. | `op` |

## ⯈ Continuous Integration & Testing

This repository provides a lightweight test harness designed to assert configuration parser invariants (`AntiBugPingSettings::fromArray`) and validate the heuristic scoring engine (`DesyncDetector::onSuspiciousAction`).

Execute the test suite via native CLI:

```bash
php tests/run.php
```
<br> <div align="center"> <h3>Developer Profile</h3> <a href="https://github.com/Jorgebyte"> <img src="https://github-readme-stats.vercel.app/api?username=Jorgebyte&show_icons=true&theme=transparent&hide_border=true&title_color=2ea44f&icon_color=2ea44f&text_color=a3a3a3" alt="Jorgebyte's GitHub Telemetry" width="400"/> </a> </div> <br> <div align="center"> <h3>Network Status</h3> <a href="https://discord.com/users/1165097093480853634"> <img src="https://lanyard.cnrad.dev/api/1165097093480853634?theme=dark&bg=0d1117&animated=true&hideDiscrim=true&borderRadius=10px" alt="Discord Telemetry"/> </a> </div>

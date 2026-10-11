# Feature Proposals & Product Roadmap
**DevBridge AI Provider for Magnitude**

---

## 1. Executive Summary

The **DevBridge AI Provider for Magnitude** connects WordPress to a local or networked [Magnitude](https://magnitude.dev/) inference engine via the official WordPress AI Client (`WordPress\AiClient`).

While the initial version (v0.1.0) provides a solid, decoupled foundation—including automatic model discovery, multi-modal capability mapping, safe local HTTP networking, and basic settings management—there are significant opportunities to expand this plugin into a feature-complete local AI management suite for WordPress administrators and developers.

This document outlines high-impact feature proposals, technical architecture designs, and a phased implementation roadmap.

---

## 2. Current Architecture Baseline

```
WordPress Ecosystem
  ├── WordPress AI Client (core / feature plugin)
  │     └── MagnitudeProvider (Provider ID: 'magnitude')
  │           ├── MagnitudeTextGenerationModel (OpenAI-compatible /v1/chat/completions)
  │           └── MagnitudeModelMetadataDirectory (Reads /v1/models)
  │                 └── Model_Capability_Mapper (Tools, JSON Schema, Vision, Audio)
  └── Admin UI & Networking
        ├── Local_Host_Allowance (Safe HTTP localhost/private IP filter)
        ├── Host_Resolver (Handles constant, option, and filter precedence)
        └── Settings_Page (Settings > Magnitude connection & models display)
```

---

## 3. Detailed Feature Proposals

### Feature 1: In-Admin AI Playground & Model Test Console
* **Priority**: High
* **Target Audience**: Site Administrators, Site Builders
* **Objective**: Allow administrators to test model responses directly in WordPress without needing third-party AI blocks or code.

#### Technical Details:
* **UI Location**: New "Test Playground" card on `Settings > Magnitude`.
* **Components**:
  * Dropdown to select any currently active model.
  * System prompt textarea (optional).
  * User prompt input with preset test buttons (*"Tell a one-sentence joke"*, *"Summarize WordPress"*, *"Return JSON data"*).
  * Output viewer displaying:
    * Formatted plain text or syntax-highlighted JSON.
    * Round-trip inference latency (e.g., `1.42s`).
    * Token count and generation throughput estimation.
* **Backend**:
  * Dedicated WordPress REST API endpoint (project rule: REST only, no admin-ajax).
  * Nonce verification and capability check (`manage_options`).
  * Invokes `MagnitudeTextGenerationModel` directly through the AI Client registry.

---

### Feature 2: WordPress Site Health Diagnostics Integration
* **Priority**: High
* **Target Audience**: System Administrators, Developers
* **Objective**: Provide automated background health checks via WordPress Site Health (`Tools > Site Health`).

#### Technical Details:
* Register tests via the `site_status_tests` filter.
* **Test Cases**:
  1. **Magnitude Daemon Reachability**: Verifies that the configured endpoint responds to HTTP requests.
  2. **Active Models Verification**: Alerts if Magnitude is running but reports 0 active/loaded models.
  3. **Insecure Remote Connection Warning**: Flags instances where an unencrypted `http://` address is configured for a non-loopback IP (e.g., remote machine over LAN or WAN).
  4. **Timeout & Latency Health**: Checks if response latency exceeds standard thresholds.

---

### Feature 3: Asynchronous AJAX Connection Check & Latency Monitor
* **Priority**: High
* **Target Audience**: All Users
* **Objective**: Eliminate full-page reloads when refreshing model status and display live connection latency.

#### Technical Details:
* Replace the `admin-post.php` form submission with a lightweight JavaScript fetch call against a REST API endpoint (project rule: REST only, no admin-ajax).
* Live status pill that shows:
  * Connection state (`Connected` / `Unreachable`).
  * Real-time HTTP ping / latency badge (e.g., `38 ms`).
  * Updated active model count and capabilities.
* Graceful fallback to standard form submit if JavaScript is disabled.

---

### Feature 4: UI-Configurable Request Timeout & Model Presets
* **Priority**: Medium
* **Target Audience**: Site Administrators
* **Objective**: Enable non-technical users to adjust timeouts and defaults without adding PHP filters.

#### Technical Details:
* Add options under `Settings > Magnitude`:
  * **Request Timeout (seconds)**: Defaults to `120`, adjustable between `10` and `600` seconds (beneficial for slower CPUs or large context models).
  * **Default Fallback Model**: Select a preferred model when calling plugins do not specify a model ID.
  * **Temperature & Top-P Defaults**: Global default overrides for generation sampling.
* Persisted in `devbridge_ai_provider_magnitude_options` or separate sanitizable options.

---

### Feature 5: Reasoning / Thinking Model Sanitizer (`<think>` Tags)
* **Priority**: Medium
* **Target Audience**: Content Creators, Developers using Reasoning Models (e.g., DeepSeek R1, Qwen 2.5 Max)
* **Objective**: Manage internal chain-of-thought tokens produced by reasoning models.

#### Technical Details:
* Reasoning models output internal reasoning between `<think>` and `</think>` tags, which can consume context or clutter front-end displays.
* Implement a response post-processor in [`MagnitudeTextGenerationModel.php`](file:///C:/Users/NEW/Downloads/wp-content/plugins/devbridge-ai-provider-magnitude/includes/Models/MagnitudeTextGenerationModel.php):
  * **Option A: Clean Output (Default)**: Strips `<think>...</think>` tags before delivering content to WordPress blocks/plugins.
  * **Option B: Preserve Raw Output**: Retains thoughts for debugging or custom display.
  * **Filter**: `apply_filters( 'devbridge_ai_provider_magnitude_strip_thinking_tags', true, $model_id )`.

---

### Feature 6: WP-CLI Command Suite (`wp magnitude`)
* **Priority**: Medium
* **Target Audience**: DevOps, Server Administrators, Headless WP Developers
* **Objective**: Enable full command-line inspection and scripting for Magnitude in WordPress.

#### Command Specifications:
* `wp magnitude status`: Checks daemon availability, active URL, and latency.
* `wp magnitude models`: Outputs an ASCII table with model names, IDs, and capability badges.
* `wp magnitude prompt <model> --prompt="<text>"`: Runs inference directly in the shell.
* `wp magnitude set-host <url>`: Sets or updates the server address.
* `wp magnitude cache-clear`: Flushes cached model directories and metadata.

---

### Feature 7: Inference Telemetry, Audit Trail & Query Inspector
* **Priority**: Medium
* **Target Audience**: Developers, Debuggers
* **Objective**: Provide visibility into requests, errors, token limits, and payload mismatches.

#### Technical Details:
* Optional diagnostic logging setting (disabled by default for privacy and performance).
* Records metadata for the last 25 requests:
  * Timestamp, model ID, request duration.
  * HTTP status code and error messages.
  * Prompt token estimate / completion tokens.
* Privacy safeguards: Automatically redacts API keys and offers an option to exclude prompt content from logs.
* In-admin log viewer with a "Clear Logs" and "Copy Debug Report" button for support tickets.

---

### Feature 8: Dynamic Embeddings & Vector Search (RAG Support)
* **Priority**: Future Exploration
* **Target Audience**: Search & Retrieval-Augmented Generation (RAG) Plugins
* **Objective**: Expand beyond chat completions to vector embeddings if supported by Magnitude.

#### Technical Details:
* Probe the `/v1/embeddings` endpoint during connection inspection.
* If supported, register `MagnitudeEmbeddingModel` implementing `WordPress\AiClient\Providers\Models\Contracts\EmbeddingModelInterface`.
* Map embedding dimensions and register capability with WordPress AI Client registry.

---

### Feature 9: Multi-Host Failover & Server Profiles
* **Priority**: Low / Advanced
* **Target Audience**: Multi-environment & High-Availability Setups
* **Objective**: Configure primary and secondary inference servers.

#### Technical Details:
* Support primary host (e.g., local workstation `http://127.0.0.1:10100`) and fallback host (e.g., local network server `http://192.168.1.100:10100`).
* Automatic failover if primary host does not respond within a configured ping threshold.

---

## 4. Implementation Roadmap

| Phase | Features | Complexity | Key Files Affected |
|---|---|---|---|
| **Phase 1: Diagnostic & Admin Enhancements** | • WordPress Site Health tests<br>• UI Configurable Timeout<br>• AJAX Connection Check | Low - Medium | `Settings_Page.php`<br>`Settings_Registrar.php`<br>`Registry_Connection_Inspector.php`<br>`assets/js/settings.js` |
| **Phase 2: Playground & Model Handling** | • Admin AI Playground Console<br>• Reasoning `<think>` Tag Sanitizer<br>• Latency Ping Badge | Medium | `MagnitudeTextGenerationModel.php`<br>`Settings_Page.php`<br>`assets/css/settings.css` |
| **Phase 3: CLI & Developer Tooling** | • WP-CLI Command Suite (`wp magnitude`)<br>• Diagnostic Request Logging | Medium | `includes/Cli/Magnitude_Cli_Command.php`<br>`includes/Logging/Inference_Logger.php` |
| **Phase 4: Advanced Inference** | • Embeddings Auto-detection<br>• Multi-Host Failover | High | `MagnitudeProvider.php`<br>`Host_Resolver.php` |

---

## 5. Coding & Standards Compliance

All new features should adhere to the established project principles:
* **PHP Requirements**: Strict typing (`declare(strict_types=1);`), compatible with PHP 7.4+.
* **Security**: Nonce validation on all actions, `current_user_can('manage_options')` checks, input sanitization with `Host_Validator`, output escaping (`esc_html`, `esc_attr`, `esc_url`).
* **Decoupling**: Implement `Hook_Registrar` for all WordPress integrations and keep business logic isolated in testable classes.
* **Translations**: Proper internationalization with `devbridge-ai-provider-magnitude` textdomain.

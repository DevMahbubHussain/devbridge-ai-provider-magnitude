=== DevBridge AI Provider for Magnitude ===
Contributors:      mahbubwpwist
Tags:              ai, magnitude, llm, local-ai, connector
Requires at least: 7.0
Tested up to:      7.1
Stable tag:        0.1.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Text generation with tool calling and image input on supported models, running locally on your own computer with Magnitude.

== Description ==

Connects WordPress to a [Magnitude](https://magnitude.dev/) server, an open-source inference engine that runs open-weight models on your own hardware, so AI features in WordPress can use a local model.

The plugin uses Magnitude's OpenAI-compatible API through the WordPress AI Client.

This plugin is an independent project. It is not affiliated with, endorsed by, or sponsored by Magnitude AI Inc., the maker of Magnitude. The name Magnitude is used here only to describe what the plugin connects to.

**Features:**

* Text generation with models served by Magnitude
* Tool (function) calling and structured JSON output on models that support them
* Image and audio input on models that Magnitude reports as accepting them
* Features follow each model automatically: whatever Magnitude reports for a model you start is what WordPress offers for it
* Automatic model discovery
* Works without an API key for local servers
* Appears under Settings > Connectors
* A Settings > Magnitude screen shows the connection status, the running models and the server address

**Requirements:**

* WordPress 7.0 or higher
* PHP 7.4 or higher
* The Magnitude app, running with at least one model downloaded and started. Magnitude only lists models that are running, so the connector shows no models until you start one.

**Good to know:**

* Magnitude runs text and chat models. Image generation, embeddings and speech are not available through Magnitude, so this connector does not offer them.
* The default server URL is `http://127.0.0.1:10100/inference`, which is where Magnitude serves its OpenAI-compatible API on the same computer as WordPress.
* The first request to a model can take a minute while it loads.
* Magnitude returns one answer per request, so asking for several variations at once is not available.
* Some models "think" before they answer. Give those a generous token limit, or they can run out of tokens before producing a reply.

== External services ==

This plugin connects to the Magnitude server whose address you set under Settings > Magnitude, or with the `DEVBRIDGE_AI_PROVIDER_MAGNITUDE_HOST` constant. By default that is `http://127.0.0.1:10100/inference`, a program you run yourself on the same computer.

* **What is sent:** the prompts and context that other plugins send through the WordPress AI Client, test prompts sent from the Test Playground, requests for the list of models, and the API key if you entered one on Settings > Connectors.
* **When:** only when a plugin uses the AI Client with Magnitude, when an administrator tests models in the Test Playground, and when an administrator opens Settings > Connectors or Settings > Magnitude or chooses Check connection. Nothing is sent when the plugin is activated or on the front end of your site.
* **Where it goes:** only to the address you configured. Your prompts go only to that address. This plugin sends no data to its author or to any other third party.

Magnitude is a separate program made by Magnitude AI Inc.: https://magnitude.dev/

== Installation ==

1. Upload the plugin to `/wp-content/plugins/devbridge-ai-provider-magnitude/`, or install it from the Plugins screen.
2. Activate the plugin.
3. Open Settings > Magnitude. The status should read Connected and your running models should be listed. No API key is needed when Magnitude runs on the same computer. For another computer, see the FAQ.

== Frequently Asked Questions ==

= Why does Magnitude show no models? =

Magnitude lists only models that are downloaded and running. Open the Magnitude app, start a model, and reload.

= Do I need an API key? =

No. When Magnitude runs on the same computer as WordPress, leave the API key box on Settings > Connectors empty. The box is shown for every connector, but Magnitude does not need a key for local use.

= How do I connect to Magnitude on another computer? =

1. In the Magnitude app on that computer, turn on Network access.
2. If "Require API key" is on, use "Copy API key" in the same screen, then paste the key into the Magnitude box on Settings > Connectors and save.
3. Note the address under "Reachable at". It ends in `/inference/v1`.
4. On your WordPress site, open Settings > Magnitude, paste that address and save. The plugin adds the `/v1` part itself, so either form works.

To fix the address in code instead, add `define( 'DEVBRIDGE_AI_PROVIDER_MAGNITUDE_HOST', 'http://192.168.1.20:10100/inference' );` to `wp-config.php`. The settings field is then locked.

Only http and https addresses without a username or password are accepted. Use this only on a network you trust.

= Why is a reply empty or cut short? =

Reasoning models spend tokens on their thinking first. Increase the maximum token limit for the feature you are using.

= How is Magnitude different from Ollama? =

Both run open-weight models on your own computer and both offer an OpenAI-compatible API, so either can power AI features in WordPress. They are separate programs made by different companies, and each has its own WordPress connector plugin.

* **Focus:** Magnitude is an inference engine that tunes itself for your specific hardware. Ollama has a large library of ready-to-pull models and also offers a cloud service.
* **Speed:** Magnitude's own benchmarks report faster generation than llama.cpp on the hardware it tested. Results depend on your computer and model, so try both if speed matters to you.
* **Default address:** This plugin uses `http://127.0.0.1:10100/inference`. The Ollama connector uses `http://localhost:11434`.
* **Features in WordPress:** This plugin offers text generation, tool calling, structured JSON output, and image and audio input where the model supports them. The Ollama connector also offers image generation and embeddings, which Magnitude does not provide.

= Which one should I use? =

Use the one that matches the program you already run. If you need embeddings or image generation, choose the Ollama connector. If you use Magnitude, or want to try it, use this plugin.

= What data does the Test Playground store? =

* **Conversation history:** When testing models in the Test Playground on Settings > Magnitude, your multi-turn conversation thread is kept strictly in your own browser's local session storage (`sessionStorage`). It is never stored on the server or in the database. Choosing "Clear History" or closing the browser tab removes it immediately.
* **Response cache:** Prompt completions are cached temporarily in WordPress transients on your server for up to 10 minutes to reduce local GPU and CPU load when testing. You can check "Bypass Cache" to skip cached results and generate fresh responses.
* **Uninstall cleanup:** When the plugin is deleted, all saved options and temporary transients are completely purged from your database across single and multisite installs.

= Is this plugin related to the Ollama connector? =

No. This is an independent plugin. It was inspired by the AI Provider for Ollama plugin and is not affiliated with Ollama, its makers, or the authors of that plugin.

== Changelog ==

= 0.1.0 =
* Initial release.
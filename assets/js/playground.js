/**
 * Magnitude Test Playground Client Controller.
 *
 * Handles client-side streaming (SSE), multi-turn conversation thread memory,
 * sampling parameter persistence, auto-JSON formatting, and telemetry calculation.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		const container = document.querySelector( '.devbridge-ai-provider-magnitude-playground' );
		if ( ! container ) {
			return;
		}

		const config = window.devbridgeMagnitudePlayground || {};
		const restUrl = config.restUrl || '';
		const nonce = config.nonce || '';
		const strings = config.strings || {};

		const STORAGE_KEY = 'devbridge_magnitude_playground_state';

		// Elements
		const modelSelect = document.getElementById( 'devbridge-playground-model' );
		const tempSlider = document.getElementById( 'param-temp' );
		const tempVal = document.getElementById( 'temp-val' );
		const topPSlider = document.getElementById( 'param-topp' );
		const topPVal = document.getElementById( 'topp-val' );
		const maxTokensInput = document.getElementById( 'param-max-tokens' );
		const bypassCacheToggle = document.getElementById( 'devbridge-playground-bypass-cache' );
		const systemTextarea = document.getElementById( 'devbridge-playground-system' );
		const historyContainer = document.getElementById( 'devbridge-playground-history' );
		const promptTextarea = document.getElementById( 'devbridge-playground-prompt' );
		const sendBtn = document.getElementById( 'devbridge-playground-send' );
		const stopBtn = document.getElementById( 'devbridge-playground-stop' );
		const clearBtn = document.getElementById( 'devbridge-playground-clear' );
		const spinner = document.getElementById( 'devbridge-playground-spinner' );
		const outputContainer = document.getElementById( 'devbridge-playground-output' );
		const outputContent = document.getElementById( 'devbridge-playground-content' );
		const statLatency = document.getElementById( 'stat-latency' );
		const statTokens = document.getElementById( 'stat-tokens' );
		const statSpeed = document.getElementById( 'stat-speed' );
		const statCached = document.getElementById( 'stat-cached' );

		let abortController = null;
		let isGenerating = false;

		// Default state
		let state = {
			model: modelSelect ? modelSelect.value : '',
			temperature: 0.7,
			top_p: 1.0,
			max_tokens: 1024,
			bypass_cache: false,
			system_prompt: '',
			turns: []
		};

		/**
		 * Loads state from sessionStorage.
		 */
		function loadState() {
			try {
				const saved = sessionStorage.getItem( STORAGE_KEY );
				if ( saved ) {
					const parsed = JSON.parse( saved );
					state = Object.assign( state, parsed );
				}
			} catch ( e ) {
				// Fall back to defaults on parse error.
			}

			// Sync UI controls with loaded state
			if ( modelSelect && state.model ) {
				modelSelect.value = state.model;
			}
			if ( tempSlider && state.temperature !== undefined ) {
				tempSlider.value = state.temperature;
				if ( tempVal ) {
					tempVal.textContent = state.temperature;
				}
			}
			if ( topPSlider && state.top_p !== undefined ) {
				topPSlider.value = state.top_p;
				if ( topPVal ) {
					topPVal.textContent = state.top_p;
				}
			}
			if ( maxTokensInput && state.max_tokens !== undefined ) {
				maxTokensInput.value = state.max_tokens;
			}
			if ( bypassCacheToggle && state.bypass_cache !== undefined ) {
				bypassCacheToggle.checked = Boolean( state.bypass_cache );
			}
			if ( systemTextarea && state.system_prompt ) {
				systemTextarea.value = state.system_prompt;
			}

			renderHistory();
		}

		/**
		 * Saves current state to sessionStorage.
		 */
		function saveState() {
			try {
				sessionStorage.setItem( STORAGE_KEY, JSON.stringify( state ) );
			} catch ( e ) {
				// Ignore storage quota errors.
			}
		}

		/**
		 * Formats and escapes HTML text.
		 *
		 * @param {string} str Raw text.
		 * @return {string} Escaped text.
		 */
		function escapeHtml( str ) {
			const div = document.createElement( 'div' );
			div.textContent = str;
			return div.innerHTML;
		}

		/**
		 * Renders conversational turn history in the viewport.
		 */
		function renderHistory() {
			if ( ! historyContainer ) {
				return;
			}
			historyContainer.innerHTML = '';

			state.turns.forEach( function ( turn ) {
				const bubble = document.createElement( 'div' );
				bubble.className = 'devbridge-playground-message devbridge-playground-message-' + turn.role;

				const label = document.createElement( 'strong' );
				label.textContent = turn.role === 'user' ? 'You: ' : 'Assistant: ';
				bubble.appendChild( label );

				const textSpan = document.createElement( 'span' );
				renderContentWithReasoning( textSpan, turn.content );
				bubble.appendChild( textSpan );

				historyContainer.appendChild( bubble );
			} );

			historyContainer.scrollTop = historyContainer.scrollHeight;
		}

		/**
		 * Isolates <think> reasoning tags into collapsible disclosures.
		 *
		 * @param {HTMLElement} target Target container element.
		 * @param {string} text Raw response text.
		 */
		function renderContentWithReasoning( target, text ) {
			const thinkRegex = /<think>([\s\S]*?)<\/think>/i;
			const match = text.match( thinkRegex );

			if ( match ) {
				const thoughtContent = match[ 1 ].trim();
				const remainingContent = text.replace( thinkRegex, '' ).trim();

				const details = document.createElement( 'details' );
				details.className = 'devbridge-ai-reasoning';

				const summary = document.createElement( 'summary' );
				summary.textContent = 'Reasoning / Thoughts';
				details.appendChild( summary );

				const pre = document.createElement( 'pre' );
				pre.textContent = thoughtContent;
				details.appendChild( pre );

				target.appendChild( details );

				const answerNode = document.createElement( 'span' );
				answerNode.textContent = remainingContent;
				target.appendChild( answerNode );
			} else {
				target.textContent = text;
			}
		}

		/**
		 * Checks if a string is valid JSON and pretty-formats it.
		 *
		 * @param {string} text Candidate text.
		 * @return {string} Formatted JSON or original text.
		 */
		function formatIfJson( text ) {
			const trimmed = text.trim();
			if ( ( trimmed.startsWith( '{' ) && trimmed.endsWith( '}' ) ) || ( trimmed.startsWith( '[' ) && trimmed.endsWith( ']' ) ) ) {
				try {
					const obj = JSON.parse( trimmed );
					return JSON.stringify( obj, null, 2 );
				} catch ( e ) {
					return text;
				}
			}
			return text;
		}

		// Event: Parameter sliders
		if ( tempSlider && tempVal ) {
			tempSlider.addEventListener( 'input', function () {
				tempVal.textContent = tempSlider.value;
				state.temperature = parseFloat( tempSlider.value );
				saveState();
			} );
		}

		if ( topPSlider && topPVal ) {
			topPSlider.addEventListener( 'input', function () {
				topPVal.textContent = topPSlider.value;
				state.top_p = parseFloat( topPSlider.value );
				saveState();
			} );
		}

		if ( maxTokensInput ) {
			maxTokensInput.addEventListener( 'change', function () {
				state.max_tokens = parseInt( maxTokensInput.value, 10 ) || 1024;
				saveState();
			} );
		}

		if ( modelSelect ) {
			modelSelect.addEventListener( 'change', function () {
				state.model = modelSelect.value;
				saveState();
			} );
		}

		if ( bypassCacheToggle ) {
			bypassCacheToggle.addEventListener( 'change', function () {
				state.bypass_cache = bypassCacheToggle.checked;
				saveState();
			} );
		}

		if ( systemTextarea ) {
			systemTextarea.addEventListener( 'input', function () {
				state.system_prompt = systemTextarea.value;
				saveState();
			} );
		}

		// Event: Presets
		const presetButtons = container.querySelectorAll( '.devbridge-ai-provider-magnitude-playground-presets [data-preset]' );
		presetButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				const preset = btn.getAttribute( 'data-preset' );
				if ( preset && promptTextarea ) {
					promptTextarea.value = preset;
					promptTextarea.focus();
				}
			} );
		} );

		// Event: Keyboard shortcut (Ctrl+Enter / Cmd+Enter)
		if ( promptTextarea ) {
			promptTextarea.addEventListener( 'keydown', function ( e ) {
				if ( ( e.ctrlKey || e.metaKey ) && e.key === 'Enter' ) {
					e.preventDefault();
					if ( ! isGenerating ) {
						handleGenerate();
					}
				}
			} );
		}

		// Event: Clear history
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				const confirmMsg = strings.clearConfirm || 'Are you sure you want to clear conversation history?';
				if ( window.confirm( confirmMsg ) ) {
					state.turns = [];
					saveState();
					renderHistory();
					if ( outputContainer ) {
						outputContainer.hidden = true;
					}
					if ( outputContent ) {
						outputContent.textContent = '';
					}
				}
			} );
		}

		// Event: Stop generation
		if ( stopBtn ) {
			stopBtn.addEventListener( 'click', function () {
				if ( abortController ) {
					abortController.abort();
					abortController = null;
				}
				finishGeneration( false );
			} );
		}

		// Event: Send prompt
		if ( sendBtn ) {
			sendBtn.addEventListener( 'click', function () {
				if ( ! isGenerating ) {
					handleGenerate();
				}
			} );
		}

		/**
		 * Main generation workflow.
		 */
		async function handleGenerate() {
			if ( ! promptTextarea || ! promptTextarea.value.trim() ) {
				return;
			}

			const userPrompt = promptTextarea.value.trim();
			promptTextarea.value = '';

			// Record user turn
			state.turns.push( { role: 'user', content: userPrompt } );
			saveState();
			renderHistory();

			// Prepare messages array for API
			const messagesPayload = [];
			if ( state.system_prompt && state.system_prompt.trim() ) {
				messagesPayload.push( { role: 'system', content: state.system_prompt.trim() } );
			}
			state.turns.forEach( function ( turn ) {
				messagesPayload.push( { role: turn.role, content: turn.content } );
			} );

			// Start UI generation state
			startGenerationUi();

			abortController = new AbortController();
			const startTime = performance.now();
			let accumulatedText = '';
			let tokenCount = 0;

			try {
				const response = await fetch( restUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce
					},
					body: JSON.stringify( {
						model: state.model || ( modelSelect ? modelSelect.value : '' ),
						messages: messagesPayload,
						temperature: state.temperature,
						top_p: state.top_p,
						max_tokens: state.max_tokens,
						bypass_cache: state.bypass_cache,
						stream: true
					} ),
					signal: abortController.signal
				} );

				if ( ! response.ok ) {
					let errorMsg = strings.errorGeneric || 'Generation request failed.';
					try {
						const errJson = await response.json();
						if ( errJson && errJson.message ) {
							errorMsg = errJson.message;
						}
					} catch ( e ) {
						// Keep default error message
					}
					throw new Error( errorMsg );
				}

				const contentType = response.headers.get( 'content-type' ) || '';

				// Handle SSE streaming
				if ( contentType.includes( 'text/event-stream' ) && response.body ) {
					const reader = response.body.getReader();
					const decoder = new TextDecoder( 'utf-8' );
					let buffer = '';

					while ( true ) {
						const { done, value } = await reader.read();
						if ( done ) {
							break;
						}

						buffer += decoder.decode( value, { stream: true } );
						const lines = buffer.split( '\n\n' );
						buffer = lines.pop() || '';

						for ( const line of lines ) {
							const trimmed = line.trim();
							if ( ! trimmed.startsWith( 'data: ' ) ) {
								continue;
							}

							const jsonStr = trimmed.slice( 6 ).trim();
							if ( ! jsonStr ) {
								continue;
							}

							let frame;
							try {
								frame = JSON.parse( jsonStr );
							} catch ( err ) {
								continue;
							}

							const isChunk = ( frame.type === 'chunk' && frame.content !== undefined ) || frame.delta !== undefined;
							const chunkContent = frame.content !== undefined ? frame.content : frame.delta;

							if ( isChunk && chunkContent ) {
								accumulatedText += chunkContent;
								tokenCount++;

								// Live update
								outputContent.textContent = accumulatedText;

								const elapsedSec = ( performance.now() - startTime ) / 1000;
								if ( elapsedSec > 0 && statSpeed ) {
									const tokPerSec = ( tokenCount / elapsedSec ).toFixed( 1 );
									statSpeed.textContent = `~${tokPerSec} tok/s`;
								}
							} else if ( frame.type === 'done' || frame.done ) {
								updateTelemetryMetrics( frame );
							} else if ( frame.type === 'error' || frame.error ) {
								throw new Error( frame.message || frame.error || strings.errorGeneric );
							}
						}
					}
				} else {
					// Single-shot JSON response fallback
					const data = await response.json();
					accumulatedText = data.text || '';
					updateTelemetryMetrics( data );
				}

				// Finalize completed response
				const formattedText = formatIfJson( accumulatedText );
				outputContent.textContent = '';
				renderContentWithReasoning( outputContent, formattedText );

				// Append assistant turn
				state.turns.push( { role: 'assistant', content: accumulatedText } );
				saveState();
				renderHistory();

				finishGeneration( true );
			} catch ( err ) {
				if ( err.name === 'AbortError' ) {
					// User cancelled - keep partial content
					if ( accumulatedText ) {
						state.turns.push( { role: 'assistant', content: accumulatedText } );
						saveState();
						renderHistory();
					}
					finishGeneration( false );
					return;
				}

				if ( outputContainer ) {
					outputContainer.hidden = false;
				}
				if ( outputContent ) {
					outputContent.textContent = `Error: ${err.message || strings.networkError || 'Network error'}`;
				}
				finishGeneration( false );
			}
		}

		/**
		 * Updates stats badges with latency and token metrics.
		 *
		 * @param {Object} metrics Done frame metrics.
		 */
		function updateTelemetryMetrics( metrics ) {
			if ( statLatency && metrics.latency_ms !== undefined ) {
				statLatency.textContent = `${metrics.latency_ms} ms`;
			}

			if ( statTokens && metrics.completion_tokens !== undefined ) {
				const promptTok = metrics.prompt_tokens || 0;
				statTokens.textContent = `${metrics.completion_tokens} tokens (in: ${promptTok})`;
			}

			if ( statSpeed && metrics.latency_ms > 0 && metrics.completion_tokens ) {
				const sec = metrics.latency_ms / 1000;
				const speed = ( metrics.completion_tokens / sec ).toFixed( 1 );
				statSpeed.textContent = `~${speed} tok/s`;
			}

			if ( statCached ) {
				statCached.hidden = ! Boolean( metrics.cached );
			}
		}

		/**
		 * Enters generating state in UI.
		 */
		function startGenerationUi() {
			isGenerating = true;
			if ( sendBtn ) {
				sendBtn.disabled = true;
				sendBtn.textContent = strings.sending || 'Generating...';
			}
			if ( stopBtn ) {
				stopBtn.hidden = false;
			}
			if ( spinner ) {
				spinner.classList.add( 'is-active' );
			}
			if ( outputContainer ) {
				outputContainer.hidden = false;
			}
			if ( outputContent ) {
				outputContent.textContent = '';
			}
			if ( statLatency ) {
				statLatency.textContent = '';
			}
			if ( statTokens ) {
				statTokens.textContent = '';
			}
			if ( statSpeed ) {
				statSpeed.textContent = '';
			}
			if ( statCached ) {
				statCached.hidden = true;
			}
		}

		/**
		 * Exits generating state in UI.
		 *
		 * @param {boolean} success Whether generation finished normally.
		 */
		function finishGeneration( success ) {
			isGenerating = false;
			abortController = null;

			if ( sendBtn ) {
				sendBtn.disabled = false;
				sendBtn.textContent = strings.send || 'Send prompt';
			}
			if ( stopBtn ) {
				stopBtn.hidden = true;
			}
			if ( spinner ) {
				spinner.classList.remove( 'is-active' );
			}
			if ( promptTextarea ) {
				promptTextarea.focus();
			}
		}

		// Initialize
		loadState();
	} );
} )();

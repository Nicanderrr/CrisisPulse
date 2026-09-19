<button id="crisis-ai-toggle" class="crisis-ai-toggle" type="button" aria-label="Open CrisisPulse Assistant">
    <span>AI</span>
</button>

<section id="crisis-ai-panel" class="crisis-ai-panel" aria-label="CrisisPulse Assistant">
    <div class="crisis-ai-head">
        <div class="crisis-ai-title-wrap">
            <span class="crisis-ai-head-icon"><i class="bi bi-heart-pulse-fill"></i></span>
            <div>
                <strong>CrisisPulse AI</strong>
                <small>Hospital communications desk</small>
            </div>
        </div>
        <div class="crisis-ai-head-actions">
            <span class="crisis-ai-secure"><i class="bi bi-shield-check"></i> Secure</span>
            <button id="crisis-ai-close" type="button" aria-label="Close assistant"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>

    <div class="crisis-ai-intro">
        <div class="crisis-ai-intro-icon"><i class="bi bi-person-heart"></i></div>
        <div><strong>How can I help?</strong><span>Ask about alerts, records, responses, or the monitoring system.</span></div>
    </div>

    <div id="crisis-ai-messages" class="crisis-ai-messages">
        <div class="crisis-ai-message assistant">Hi, I am your CrisisPulse assistant. Ask me to summarize alerts, explain the system, draft a response, or analyze a new text report.</div>
    </div>

    <div class="crisis-ai-vitals">
        <div id="crisis-ai-wave" class="crisis-ai-wave" aria-hidden="true">
            <svg viewBox="0 0 240 54" preserveAspectRatio="none"><polyline points="0,28 34,28 48,28 58,26 66,29 75,28 88,28 98,28 107,7 115,47 124,18 132,28 166,28 179,28 189,26 197,29 207,28 240,28"></polyline></svg>
        </div>
        <div class="crisis-ai-vitals-copy"><span>Voice channel</span><strong id="crisis-ai-status">System-aware assistant</strong></div>
        <span class="crisis-ai-live-badge"><i></i> Live</span>
    </div>
    <div id="crisis-ai-live-transcript" class="crisis-ai-live-transcript"><span>Live transcript</span><strong>Start voice and speak naturally.</strong></div>

    <div id="crisis-ai-shortcuts" class="crisis-ai-shortcuts">
        <button type="button" data-tool="dashboard_summary">Summary</button>
        <button type="button" data-tool="list_messages" data-filter="high_risk">High risk</button>
        <button type="button" data-tool="list_messages" data-filter="negative">Negative</button>
        <button type="button" data-tool="explain_system">Explain</button>
    </div>

    <div class="crisis-ai-voice">
        <button id="crisis-ai-voice" type="button"><i class="bi bi-mic-fill"></i><span>Start voice</span></button>
        <button id="crisis-ai-stop-voice" type="button" aria-label="Stop voice"><i class="bi bi-stop-fill"></i></button>
        <span id="crisis-ai-transcript">Voice control is ready.</span>
    </div>

    <form id="crisis-ai-form" class="crisis-ai-form">
        <input id="crisis-ai-input" type="text" autocomplete="off" placeholder="Ask CrisisPulse Assistant...">
        <button type="submit" aria-label="Send message"><i class="bi bi-arrow-up"></i></button>
    </form>
</section>

<script>
(() => {
    const routes = {
        boot: @json(route('assistant.boot')),
        message: @json(route('assistant.message')),
        action: @json(route('assistant.action')),
        realtime: @json(route('assistant.realtime.call')),
    };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const toggle = document.getElementById('crisis-ai-toggle');
    const panel = document.getElementById('crisis-ai-panel');
    const close = document.getElementById('crisis-ai-close');
    const form = document.getElementById('crisis-ai-form');
    const input = document.getElementById('crisis-ai-input');
    const messages = document.getElementById('crisis-ai-messages');
    const shortcuts = document.getElementById('crisis-ai-shortcuts');
    const status = document.getElementById('crisis-ai-status');
    const voiceButton = document.getElementById('crisis-ai-voice');
    const stopVoiceButton = document.getElementById('crisis-ai-stop-voice');
    const transcript = document.getElementById('crisis-ai-transcript');
    const liveTranscript = document.getElementById('crisis-ai-live-transcript');
    const wave = document.getElementById('crisis-ai-wave');
    const state = { context: null, busy: false, recognition: null, listening: false, speaking: false, voiceEnabled: false, incident: null, pendingPrompt: null, pc: null, dc: null, stream: null, audio: null, meter: null, calls: new Map(), completed: new Set(), connected: false, connecting: false, greeted: false };

    function addMessage(text, who = 'assistant') {
        const row = document.createElement('div');
        row.className = `crisis-ai-message ${who}`;
        row.textContent = text;
        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;
    }

    function speak(text) {
        if (!('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)) {
            transcript.textContent = 'Read Aloud is not supported in this browser. Try Chrome or Microsoft Edge.';
            return false;
        }

        state.speaking = true;
        if (state.listening) {
            state.recognition?.stop();
        }
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(String(text).replace(/\s+/g, ' ').slice(0, 900));
        const voice = selectFemaleVoice();
        if (voice) utterance.voice = voice;
        utterance.rate = 0.96;
        utterance.pitch = 1;
        utterance.onend = () => {
            state.speaking = false;
            updateIncidentReadButton(false);
            if (state.voiceEnabled) {
                window.setTimeout(() => {
                    if (state.voiceEnabled && !state.listening) startVoice();
                }, 400);
            }
        };
        utterance.onerror = () => {
            state.speaking = false;
            updateIncidentReadButton(false);
            transcript.textContent = 'The browser could not start audio. Check that your device volume is on.';
            if (state.voiceEnabled) {
                window.setTimeout(() => {
                    if (state.voiceEnabled && !state.listening) startVoice();
                }, 400);
            }
        };
        updateIncidentReadButton(true);
        window.speechSynthesis.speak(utterance);
        return true;
    }

    function updateIncidentReadButton(isSpeaking) {
        const button = document.querySelector('[data-incident-read-aloud]');
        if (!button) return;

        button.innerHTML = isSpeaking
            ? '<i class="bi bi-volume-up-fill me-1"></i>Speaking...'
            : '<i class="bi bi-volume-up me-1"></i>Read Aloud';
        button.disabled = isSpeaking;
    }

    function selectFemaleVoice() {
        state.voices = window.speechSynthesis?.getVoices?.() || [];
        return state.voices.find((voice) => /female|zira|susan|samantha|aria|jenny|natasha|sonia|hazel/i.test(voice.name))
            || state.voices.find((voice) => /en-/i.test(voice.lang))
            || state.voices[0]
            || null;
    }

    function setBusy(isBusy) {
        state.busy = isBusy;
        status.textContent = isBusy ? 'Thinking...' : 'System-aware assistant';
        form.querySelector('button').disabled = isBusy;
    }

    function setVoiceState(label, mode = '') {
        status.textContent = label;
        panel.dataset.voiceState = mode;
        wave?.classList.toggle('active', ['listening', 'thinking', 'speaking'].includes(mode));
        wave?.classList.toggle('speaking', mode === 'speaking');
        const voiceLabel = voiceButton.querySelector('span');
        if (voiceLabel) voiceLabel.textContent = state.connected ? 'Voice online' : (state.connecting ? 'Connecting...' : 'Start voice');
    }

    function showLiveTranscript(text, label = 'Live transcript') {
        if (!liveTranscript) return;
        liveTranscript.querySelector('span').textContent = label;
        liveTranscript.querySelector('strong').textContent = text || 'Listening for speech...';
    }

    function sendRealtime(event) {
        if (state.dc?.readyState === 'open') state.dc.send(JSON.stringify(event));
    }

    function normalizeSdp(sdp) {
        return String(sdp || '').replace(/^\uFEFF/, '').trim().replace(/\r\n|\r|\n/g, '\r\n') + '\r\n';
    }

    function respondToTool(callId, output) {
        sendRealtime({type: 'conversation.item.create', item: {type: 'function_call_output', call_id: callId, output: JSON.stringify(output)}});
        sendRealtime({type: 'response.create', response: {instructions: 'Explain the CrisisPulse tool result briefly and naturally.'}});
    }

    async function executeRealtimeTool(call) {
        const key = call.call_id || call.id;
        if (key && state.completed.has(key)) return;
        if (key) state.completed.add(key);

        let args = {};
        try { args = JSON.parse(call.arguments || '{}'); } catch (_) {}
        try {
            const result = await post(routes.action, {tool: call.name, arguments: args});
            if (result.url && result.status === 'opening') {
                addMessage(`Opening ${result.page}...`);
                window.setTimeout(() => window.CrisisPulseNavigate?.(result.url) || window.location.assign(result.url), 700);
            }
            respondToTool(call.call_id, result);
        } catch (error) {
            respondToTool(call.call_id, {error: error.message});
        }
    }

    function onRealtimeEvent(event) {
        if (event.type === 'session.created' || event.type === 'session.updated') setVoiceState('Voice online', 'ready');
        if (event.type === 'input_audio_buffer.speech_started') { showLiveTranscript('Listening for speech...', 'Live transcript'); setVoiceState('Listening...', 'listening'); }
        if (event.type === 'input_audio_buffer.speech_stopped') setVoiceState('Understanding...', 'thinking');
        if (event.type === 'response.audio.delta' || event.type === 'response.output_audio.delta') setVoiceState('Speaking...', 'speaking');
        if (['response.audio_transcript.delta', 'response.output_audio_transcript.delta', 'response.output_text.delta'].includes(event.type)) {
            if (!messages.lastElementChild?.classList.contains('crisis-ai-stream')) {
                const row = document.createElement('div');
                row.className = 'crisis-ai-message assistant crisis-ai-stream';
                messages.appendChild(row);
            }
            messages.lastElementChild.textContent += event.delta || '';
            messages.scrollTop = messages.scrollHeight;
        }
        if (['response.audio_transcript.done', 'response.output_audio_transcript.done', 'response.output_text.done'].includes(event.type)) messages.lastElementChild?.classList.remove('crisis-ai-stream');
        if (event.type === 'conversation.item.input_audio_transcription.completed' && event.transcript) {
            showLiveTranscript(event.transcript, 'CrisisPulse heard');
            addMessage(event.transcript, 'user');
        }
        if (event.type === 'conversation.item.input_audio_transcription.failed') showLiveTranscript(event.error?.message || 'Transcription failed.', 'Transcript error');
        if (event.type === 'response.function_call_arguments.delta') {
            const call = state.calls.get(event.item_id) || {name: event.name, call_id: event.call_id, arguments: ''};
            call.arguments += event.delta || '';
            call.name = event.name || call.name;
            call.call_id = event.call_id || call.call_id;
            state.calls.set(event.item_id, call);
        }
        if (event.type === 'response.function_call_arguments.done') {
            const call = state.calls.get(event.item_id) || {};
            executeRealtimeTool({...call, ...event});
            state.calls.delete(event.item_id);
        }
        if (event.type === 'response.output_item.done' && event.item?.type === 'function_call') executeRealtimeTool(event.item);
        if (event.type === 'response.done') {
            setVoiceState('Ready. Speak naturally.', 'ready');
            updateIncidentReadButton(false);
        }
        if (event.type === 'error') { setVoiceState('Voice needs attention', 'error'); addMessage(event.error?.message || 'Voice assistant error.'); }
    }

    function startMicMeter(stream) {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext || !wave) return;
        const context = new AudioContext();
        const analyser = context.createAnalyser();
        const source = context.createMediaStreamSource(stream);
        analyser.fftSize = 256;
        const data = new Uint8Array(analyser.fftSize);
        source.connect(analyser);
        const bars = [...wave.querySelectorAll('span')];
        const base = [12, 18, 26, 34, 26, 18, 12];
        const tick = () => {
            analyser.getByteTimeDomainData(data);
            let sum = 0;
            data.forEach(value => { const normalized = (value - 128) / 128; sum += normalized * normalized; });
            const level = Math.sqrt(sum / data.length);
            bars.forEach((bar, index) => { bar.style.height = `${Math.round(base[index] * (1 + Math.min(1.8, level * (9 + index))))}px`; });
            state.meter.raf = requestAnimationFrame(tick);
        };
        state.meter = {context, raf: requestAnimationFrame(tick)};
    }

    function stopMicMeter() {
        if (!state.meter) return;
        cancelAnimationFrame(state.meter.raf);
        state.meter.context.close().catch(() => null);
        state.meter = null;
        wave?.querySelectorAll('span').forEach(bar => { bar.style.height = ''; });
    }

    function greetRealtime() {
        if (state.greeted || !state.connected) return;
        state.greeted = true;
        if (state.pendingPrompt) {
            const prompt = state.pendingPrompt;
            state.pendingPrompt = null;
            sendRealtime({type: 'response.create', response: {instructions: prompt}});
            return;
        }
        addMessage('Voice assistant is ready.');
        sendRealtime({type: 'response.create', response: {instructions: 'Introduce yourself as CrisisPulse AI in one short, warm sentence, then ask how you can help with the hospital communication monitor.'}});
    }

    async function connectRealtime() {
        if (state.connected || state.connecting) return;
        state.connecting = true;
        state.greeted = false;
        panel.classList.add('open');
        setVoiceState('Requesting microphone...', 'thinking');
        const pc = new RTCPeerConnection();
        const audio = state.audio || document.createElement('audio');
        state.audio = audio;
        audio.autoplay = true;
        audio.playsInline = true;
        audio.volume = 1;
        if (!audio.isConnected) { audio.hidden = true; document.body.appendChild(audio); }
        try {
            pc.ontrack = event => {
                audio.srcObject = event.streams[0] || new MediaStream([event.track]);
                audio.play().catch(() => setVoiceState('Tap Start voice to enable sound', 'ready'));
            };
            const stream = await navigator.mediaDevices.getUserMedia({audio: {echoCancellation: true, noiseSuppression: true, autoGainControl: true}});
            pc.addTrack(stream.getAudioTracks()[0], stream);
            startMicMeter(stream);
            const dc = pc.createDataChannel('oai-events');
            dc.onmessage = message => { try { onRealtimeEvent(JSON.parse(message.data)); } catch (_) {} };
            dc.onopen = () => { state.connected = true; state.connecting = false; setVoiceState('Voice online. Speak naturally.', 'ready'); greetRealtime(); };
            dc.onclose = () => { state.connected = false; setVoiceState('Voice disconnected', 'ready'); };
            state.pc = pc; state.dc = dc; state.stream = stream;
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            const incident = currentIncidentFromPage();
            const response = await fetch(routes.realtime, {method: 'POST', credentials: 'same-origin', headers: {'Accept': 'application/sdp', 'Content-Type': 'application/sdp', 'X-CSRF-TOKEN': csrf, 'X-CrisisPulse-Page': window.location.pathname, 'X-CrisisPulse-Incident': incident?.id || ''}, body: offer.sdp});
            const answer = normalizeSdp(await response.text());
            if (!response.ok) throw new Error(answer || 'Voice setup failed on the server.');
            if (!answer.startsWith('v=0')) throw new Error('Voice setup did not receive a valid WebRTC answer.');
            await pc.setRemoteDescription({type: 'answer', sdp: answer});
        } catch (error) {
            state.connecting = false;
            state.connected = false;
            pc.close();
            state.stream?.getTracks().forEach(track => track.stop());
            stopMicMeter();
            updateIncidentReadButton(false);
            setVoiceState('Connection failed', 'error');
            addMessage(error.message);
        }
    }

    function disconnectRealtime() {
        sendRealtime({type: 'response.cancel'});
        state.dc?.close();
        state.pc?.close();
        state.stream?.getTracks().forEach(track => track.stop());
        stopMicMeter();
        state.pc = state.dc = state.stream = null;
        state.connected = false;
        state.connecting = false;
        state.greeted = false;
        setVoiceState('Voice stopped', 'ready');
        showLiveTranscript('Start voice and speak naturally.');
    }

    async function post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(body || {}),
        });
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || 'Assistant request failed.');
        }

        return data;
    }

    function summarizeResult(tool, result) {
        if (result.error) return result.error;
        if (result.url) return `Opening ${result.page}...`;
        if (tool === 'explain_system') return `${result.summary}\n\n${(result.steps || []).map((step, index) => `${index + 1}. ${step}`).join('\n')}`;
        if (tool === 'dashboard_summary') return `Here is the current picture: ${result.total_messages} total record(s), ${result.negative} negative, ${result.high_risk} high-risk, and ${result.medium_risk} medium-risk. Latest record: ${result.latest_recorded_at || 'none yet'}.`;
        if (tool === 'list_messages') {
            return (result.messages || []).length
                ? result.messages.map((item) => `#${item.id} ${item.source}: ${item.sentiment}, ${item.crisis_level}. ${item.summary || item.content_preview || 'No summary.'}`).join('\n')
                : 'No matching records found.';
        }
        if (tool === 'draft_response') return `Draft response:\n${result.draft}`;
        if (tool === 'analyze_text') return `I created and analyzed message #${result.message_id}. Sentiment: ${result.sentiment}. Crisis level: ${result.crisis_level}.`;

        return 'Done.';
    }

    function currentIncidentFromPage() {
        if (state.incident?.id) return state.incident;

        const incidentButton = document.querySelector('[data-incident-read-aloud]');
        if (!incidentButton) return null;

        try {
            state.incident = JSON.parse(incidentButton.dataset.incident || '{}');
            return state.incident?.id ? state.incident : null;
        } catch (_) {
            return null;
        }
    }

    function readCurrentIncidentAnalysis() {
        const incident = currentIncidentFromPage();
        if (!incident) {
            const message = 'I cannot see a message record on this page. Open a message analysis page first, then ask me to read it.';
            addMessage(message);
            speak(message);
            return true;
        }

        state.incident = incident;
        state.voiceEnabled = false;
        const reply = [
            `Original message: ${incident.content || 'No original message text was provided for this record.'}`,
            `Analysis summary: ${incident.summary || 'No summary is available.'}`,
            `Sentiment: ${incident.sentiment || 'unknown'}. Crisis level: ${incident.crisis_level || 'unknown'}.`,
            incident.findings ? `Detailed AI findings: ${incident.findings}` : '',
            incident.recommended_response ? `Recommended response: ${incident.recommended_response}` : '',
        ].filter(Boolean).join('\n\n');

        addMessage(reply);
        speak(reply);
        return true;
    }

    async function runTool(tool, argumentsValue = {}, shouldSpeak = state.voiceEnabled) {
        setBusy(true);
        try {
            const result = await post(routes.action, {tool, arguments: argumentsValue});
            const reply = summarizeResult(tool, result);
            addMessage(reply);
            if (shouldSpeak) speak(reply);
            if (result.url && result.status === 'opening') {
                window.location.assign(result.url);
            }
        } catch (error) {
            addMessage(error.message);
            if (shouldSpeak) speak(error.message);
        } finally {
            setBusy(false);
        }
    }

    function quickIntent(message) {
        const lower = message.toLowerCase();
        const draftMatch = lower.match(/(?:draft|write).*(?:message|record|alert)\s*#?\s*(\d+)/);
        const analyzeMatch = message.match(/^(?:analyze|record|create alert|add report)\s*:\s*(.+)$/i);

        if (/(read|say|speak|tell me).*(current|this|page|original|message|analysis|incident)/.test(lower)) return ['read_current_incident', {}];
        if (/(open|go to|show).*(dashboard)/.test(lower)) return ['open_page', {page: 'dashboard'}];
        if (/(open|go to|show).*(messages|records)/.test(lower)) return ['open_page', {page: 'messages'}];
        if (/(summary|summarize|dashboard|overview)/.test(lower)) return ['dashboard_summary', {}];
        if (/(high risk|critical|urgent|serious)/.test(lower)) return ['list_messages', {filter: 'high_risk', limit: 5}];
        if (/(negative|complaints|bad sentiment)/.test(lower)) return ['list_messages', {filter: 'negative', limit: 5}];
        if (/(latest|recent|new records)/.test(lower)) return ['list_messages', {filter: 'latest', limit: 5}];
        if (/(explain|how does|what is)/.test(lower)) return ['explain_system', {}];
        if (draftMatch) return ['draft_response', {message_id: Number(draftMatch[1])}];
        if (analyzeMatch) return ['analyze_text', {content: analyzeMatch[1], source: 'Assistant Entry'}];

        return null;
    }

    async function handleMessage(message, shouldSpeak = state.voiceEnabled) {
        if (!message || state.busy) return;

        panel.classList.add('open');
        addMessage(message, 'user');

        const intent = quickIntent(message);
        if (intent) {
            if (intent[0] === 'read_current_incident') {
                readCurrentIncidentAnalysis();
                return;
            }
            await runTool(intent[0], intent[1], shouldSpeak);
            return;
        }

        setBusy(true);
        try {
            const result = await post(routes.message, {
                message,
                incident_id: state.incident?.id || null,
            });
            const reply = result.reply || 'I could not generate a reply right now.';
            addMessage(reply);
            if (shouldSpeak) speak(reply);
        } catch (error) {
            addMessage(error.message);
            if (shouldSpeak) speak(error.message);
        } finally {
            setBusy(false);
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = input.value.trim();
        input.value = '';
        await handleMessage(message);
    });

    shortcuts.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-tool]');
        if (!button || state.busy) return;

        const tool = button.dataset.tool;
        const args = {};
        if (button.dataset.filter) args.filter = button.dataset.filter;
        runTool(tool, args);
    });

    function startVoice() {
        if (!SpeechRecognition) {
            const message = 'Voice control is not supported in this browser. Please use Chrome or Microsoft Edge.';
            transcript.textContent = message;
            addMessage(message);
            speak(message);
            return;
        }

        state.voiceEnabled = true;
        panel.classList.add('open');
        if (state.speaking) {
            transcript.textContent = 'I will listen after I finish speaking.';
            return;
        }
        if (state.listening) return;

        const recognition = new SpeechRecognition();
        recognition.lang = 'en-US';
        recognition.continuous = true;
        recognition.interimResults = true;
        state.recognition = recognition;
        state.listening = true;
        voiceButton.querySelector('span')?.replaceChildren(document.createTextNode('Listening'));
        transcript.textContent = state.incident ? 'Listening for questions about this incident.' : 'Listening. Speak your command naturally.';
        status.textContent = 'Listening...';

        recognition.onresult = (event) => {
            let interim = '';
            let finalText = '';

            for (let index = event.resultIndex; index < event.results.length; index += 1) {
                const text = event.results[index][0].transcript;
                if (event.results[index].isFinal) {
                    finalText += text;
                } else {
                    interim += text;
                }
            }

            transcript.textContent = finalText || interim || 'Listening...';

            if (finalText.trim()) {
                transcript.textContent = finalText.trim();
                handleMessage(finalText.trim(), true);
            }
        };

        recognition.onerror = (event) => {
            const message = event.error === 'not-allowed'
                ? 'Microphone permission was blocked. Allow microphone access and try again.'
                : `Voice recognition error: ${event.error}`;
            transcript.textContent = message;
            addMessage(message);
            state.listening = false;
            voiceButton.querySelector('span')?.replaceChildren(document.createTextNode('Start voice'));
            status.textContent = 'System-aware assistant';
        };

        recognition.onend = () => {
            state.listening = false;
            voiceButton.textContent = 'Talk';
            if (state.voiceEnabled && !state.speaking) {
                window.setTimeout(() => {
                    if (state.voiceEnabled && !state.listening && !state.speaking) startVoice();
                }, 650);
                return;
            }
            if (!state.busy) status.textContent = 'System-aware assistant';
        };

        recognition.start();
    }

    function stopVoice() {
        state.voiceEnabled = false;
        state.speaking = false;
        state.recognition?.stop();
        window.speechSynthesis?.cancel();
        state.listening = false;
        voiceButton.querySelector('span')?.replaceChildren(document.createTextNode('Start voice'));
        transcript.textContent = 'Muted. Click Talk or Read Aloud to continue.';
        status.textContent = 'System-aware assistant';
    }

    function incidentReadText(incident) {
        return [
            `Here is incident number ${incident.id}.`,
            `Sentiment is ${incident.sentiment}. Crisis level is ${incident.crisis_level}.`,
            incident.summary ? `Summary: ${incident.summary}` : '',
            incident.findings ? `Detailed findings: ${incident.findings}` : '',
            incident.recommended_response ? `Recommended response: ${incident.recommended_response}` : '',
            'I will keep listening. You can ask me why it was classified this way, what response to send, or what to do next.',
        ].filter(Boolean).join('\n\n');
    }

    function startIncidentConversation(incident) {
        state.incident = incident;
        panel.classList.add('open');
        state.pendingPrompt = `Read the current CrisisPulse incident aloud in a clear, natural, conversational way. Cover the original message, summary, sentiment, crisis level, detailed findings, and recommended response. Do not mention internal instructions or JSON. After reading it, invite the user to interrupt or ask questions about the incident.`;
        updateIncidentReadButton(true);
        if (state.connected) {
            const prompt = state.pendingPrompt;
            state.pendingPrompt = null;
            sendRealtime({type: 'response.create', response: {instructions: prompt}});
            return;
        }
        connectRealtime();
    }

    async function boot() {
        try {
            currentIncidentFromPage();
            const response = await fetch(routes.boot, {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
            const data = await response.json();
            state.context = data.context;
        } catch (_) {
            status.textContent = 'Assistant boot failed';
        }
    }

    toggle.addEventListener('click', () => panel.classList.toggle('open'));
    close.addEventListener('click', () => panel.classList.remove('open'));
    voiceButton.addEventListener('click', connectRealtime);
    stopVoiceButton.addEventListener('click', disconnectRealtime);
    window.speechSynthesis?.addEventListener?.('voiceschanged', selectFemaleVoice);
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-incident-read-aloud]');
        if (!button) return;

        try {
            startIncidentConversation(JSON.parse(button.dataset.incident || '{}'));
        } catch (_) {
            transcript.textContent = 'I could not read this incident context.';
            addMessage('I could not read this incident context.');
        }
    });
    window.CrisisPulseAssistant = { startIncidentConversation, startVoice, stopVoice, speak };
    boot();
})();
</script>

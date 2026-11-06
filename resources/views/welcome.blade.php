<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHMCS AI Support Ticket Auto-Resolver — 70 Issues Playground</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
    </style>
</head>
<body class="h-full flex flex-col font-sans antialiased">

    <!-- Top Navigation Bar -->
    <header class="bg-slate-800/80 backdrop-blur border-b border-slate-700/60 sticky top-0 z-30 px-6 py-3.5 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div class="w-9 h-9 rounded-lg bg-gradient-to-tr from-indigo-500 to-sky-400 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20">
                <i class="fa-solid fa-robot text-lg"></i>
            </div>
            <div>
                <h1 class="font-bold text-slate-100 text-base leading-tight">WHMCS AI Support Ticket Auto-Resolver</h1>
                <p class="text-xs text-slate-400">70 Hosting Issues Local Testing Suite • Supabase pgvector RAG + Gemini 3.1 Flash-Lite</p>
            </div>
        </div>

        <div class="flex items-center space-x-3 text-xs">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 mr-1.5 animate-pulse"></span>
                RAG Vector DB: Active
            </span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-sky-500/10 text-sky-400 border border-sky-500/20">
                <i class="fa-solid fa-bolt mr-1.5"></i>
                Gemini AI: Connected
            </span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">
                HZ-AI-SUB-2026-LIVE
            </span>
        </div>
    </header>

    <!-- Main Workspace Container -->
    <div class="flex-1 flex overflow-hidden">
        
        <!-- Left Sidebar: 70 Issues Browser -->
        <aside class="w-96 border-r border-slate-700/60 bg-slate-900/90 flex flex-col">
            <!-- Search & Filter Header -->
            <div class="p-4 border-b border-slate-700/60 space-y-3">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-xs"></i>
                    <input type="text" id="searchInput" placeholder="Search 70 issues..." 
                           class="w-full bg-slate-800/80 border border-slate-700 rounded-lg pl-8 pr-3 py-2 text-xs text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="flex items-center justify-between text-xs text-slate-400">
                    <span>Catalogue: <strong class="text-slate-200">70 Issues</strong></span>
                    <span id="activeCount" class="text-indigo-400">Showing 70</span>
                </div>
            </div>

            <!-- Issues List -->
            <div id="issuesList" class="flex-1 overflow-y-auto p-3 space-y-2">
                @foreach ($issues as $item)
                    <div class="issue-card p-3 rounded-lg border border-slate-700/50 bg-slate-800/40 hover:bg-slate-800 hover:border-indigo-500/50 cursor-pointer transition text-left group"
                         data-id="{{ $item['id'] }}"
                         data-category="{{ $item['category'] }}"
                         data-title="{{ $item['title'] }}"
                         data-subject="{{ $item['subject'] }}"
                         data-message="{{ $item['message'] }}"
                         data-invoices="{{ $item['invoices'] }}"
                         data-client-name="{{ $item['client_name'] }}"
                         data-client-email="{{ $item['client_email'] }}"
                         onclick="selectIssue(this)">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-indigo-500/20 text-indigo-300">
                                #{{ $item['id'] }}
                            </span>
                            <span class="text-[10px] text-slate-400 truncate max-w-[170px]">
                                {{ $item['category'] }}
                            </span>
                        </div>
                        <h4 class="text-xs font-medium text-slate-200 group-hover:text-indigo-300 transition leading-snug">
                            {{ $item['title'] }}
                        </h4>
                    </div>
                @endforeach
            </div>
        </aside>

        <!-- Right Content Area: Ticket Editor & Live AI Inspector -->
        <main class="flex-1 overflow-y-auto p-6 space-y-6">

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

                <!-- Column 1: Ticket Inputs -->
                <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-ticket text-indigo-400 text-sm"></i>
                            <h2 class="text-sm font-semibold text-slate-100">Ticket Payload (WHMCS Simulation)</h2>
                        </div>
                        <span id="activeBadge" class="text-xs px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 font-mono">
                            Issue #49 Loaded
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Client Name</label>
                            <input type="text" id="clientName" value="Muhammad Ali" 
                                   class="w-full bg-slate-900/80 border border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Client Email</label>
                            <input type="email" id="clientEmail" value="client49@domain.com" 
                                   class="w-full bg-slate-900/80 border border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Client WHMCS Invoices Context</label>
                        <input type="text" id="invoicesSummary" value="INV-149: $60.00 (Paid)" 
                               class="w-full bg-slate-900/80 border border-slate-700 rounded-lg px-3 py-2 text-xs font-mono text-emerald-400 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Ticket Subject</label>
                        <input type="text" id="ticketSubject" value="Website Error: 500 Internal Server Error / .htaccess issue" 
                               class="w-full bg-slate-900/80 border border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Ticket Message (Customer Query)</label>
                        <textarea id="ticketMessage" rows="5" 
                                  class="w-full bg-slate-900/80 border border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-indigo-500 leading-relaxed">Emergency: Meri live WordPress site par error a raha hai: '500 Internal Server Error / .htaccess issue'. Site open nahi ho rahi.</textarea>
                    </div>

                    <button id="runBtn" onclick="runAnalysis()" 
                            class="w-full py-2.5 px-4 bg-gradient-to-r from-indigo-500 to-sky-500 hover:from-indigo-600 hover:to-sky-600 text-white font-medium rounded-lg text-xs flex items-center justify-center space-x-2 shadow-lg shadow-indigo-500/20 transition active:scale-[0.99]">
                        <i class="fa-solid fa-play"></i>
                        <span>Run AI & Supabase RAG Analysis</span>
                    </button>
                </div>

                <!-- Column 2: Live AI Resolution Output -->
                <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-5 space-y-4 flex flex-col">
                    <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-brain text-sky-400 text-sm"></i>
                            <h2 class="text-sm font-semibold text-slate-100">Live AI Output & Verification</h2>
                        </div>
                        <span id="execTimer" class="text-xs text-slate-400 font-mono">
                            Ready
                        </span>
                    </div>

                    <!-- Metrics Badges Bar -->
                    <div class="grid grid-cols-4 gap-2 text-center">
                        <div class="bg-slate-900/60 border border-slate-700/60 rounded-lg p-2">
                            <div class="text-[10px] text-slate-400 uppercase font-bold">Department</div>
                            <div id="badgeDept" class="text-xs font-bold text-sky-400 mt-0.5">--</div>
                        </div>
                        <div class="bg-slate-900/60 border border-slate-700/60 rounded-lg p-2">
                            <div class="text-[10px] text-slate-400 uppercase font-bold">Priority</div>
                            <div id="badgePriority" class="text-xs font-bold text-amber-400 mt-0.5">--</div>
                        </div>
                        <div class="bg-slate-900/60 border border-slate-700/60 rounded-lg p-2">
                            <div class="text-[10px] text-slate-400 uppercase font-bold">Customer Mood</div>
                            <div id="badgeMood" class="text-xs font-bold text-emerald-400 mt-0.5">--</div>
                        </div>
                        <div class="bg-slate-900/60 border border-slate-700/60 rounded-lg p-2">
                            <div class="text-[10px] text-slate-400 uppercase font-bold">Remaining Quota</div>
                            <div id="badgeQuota" class="text-xs font-bold text-indigo-400 font-mono mt-0.5">Live</div>
                        </div>
                    </div>

                    <!-- Step-by-Step Reasoning -->
                    <div>
                        <div class="flex items-center space-x-1.5 text-xs text-slate-400 mb-1">
                            <i class="fa-solid fa-lightbulb text-amber-400"></i>
                            <span class="font-medium">Chain-of-Thought Reasoning:</span>
                        </div>
                        <div id="outReasoning" class="p-3 bg-slate-900/80 border border-slate-700/60 rounded-lg text-xs text-slate-300 leading-relaxed min-h-[50px]">
                            Select any of the 70 issues on the left and click "Run AI Analysis" to inspect live decision making.
                        </div>
                    </div>

                    <!-- Matched KB Article -->
                    <div>
                        <div class="flex items-center space-x-1.5 text-xs text-slate-400 mb-1">
                            <i class="fa-solid fa-database text-emerald-400"></i>
                            <span class="font-medium">Matched Supabase Knowledge Base Article:</span>
                        </div>
                        <div id="outKbDoc" class="p-2.5 bg-slate-900/80 border border-slate-700/60 rounded-lg text-xs font-mono text-emerald-300">
                            None
                        </div>
                    </div>

                    <!-- Suggested Customer Reply -->
                    <div class="flex-1 flex flex-col">
                        <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
                            <div class="flex items-center space-x-1.5">
                                <i class="fa-solid fa-reply text-indigo-400"></i>
                                <span class="font-medium">Suggested Customer Reply (AI Generated):</span>
                            </div>
                            <button onclick="copyReply()" class="text-indigo-400 hover:text-indigo-300 text-[11px] flex items-center space-x-1">
                                <i class="fa-regular fa-copy"></i>
                                <span>Copy Reply</span>
                            </button>
                        </div>
                        <div id="outReply" class="flex-1 p-3 bg-slate-900/90 border border-slate-700/60 rounded-lg text-xs text-slate-100 whitespace-pre-wrap leading-relaxed min-h-[120px]">
                            Generated reply will appear here...
                        </div>
                    </div>

                </div>

            </div>

            <!-- WHMCS Internal Staff Note Simulation -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 space-y-3">
                <div class="flex items-center justify-between border-b border-slate-700/60 pb-2">
                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-shield-halved text-emerald-400 text-sm"></i>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">WHMCS Internal Staff Note Preview (Human-in-the-Loop)</h3>
                    </div>
                    <span class="text-[11px] text-slate-400">Added to WHMCS via localAPI('AddTicketReply', ['noemail' => true])</span>
                </div>
                <pre id="whmcsStaffNote" class="p-3 bg-slate-950/80 border border-slate-800 rounded-lg text-xs font-mono text-emerald-400 overflow-x-auto whitespace-pre-wrap leading-relaxed">
[AI Auto-Analysis & Verified KB Solution]
--------------------------------------------------
Reasoning: Waiting for execution...
Suggested Reply will be drafted internally for human review before sending.
                </pre>
            </div>

        </main>

    </div>

    <!-- Interactive JavaScript -->
    <script>
        let currentIssueId = 49;

        function selectIssue(card) {
            document.querySelectorAll('.issue-card').forEach(c => {
                c.classList.remove('border-indigo-500', 'bg-slate-800');
            });
            card.classList.add('border-indigo-500', 'bg-slate-800');

            currentIssueId = card.dataset.id;
            document.getElementById('activeBadge').innerText = `Issue #${currentIssueId} Loaded`;
            document.getElementById('clientName').value = card.dataset.clientName;
            document.getElementById('clientEmail').value = card.dataset.clientEmail;
            document.getElementById('invoicesSummary').value = card.dataset.invoices;
            document.getElementById('ticketSubject').value = card.dataset.subject;
            document.getElementById('ticketMessage').value = card.dataset.message;
        }

        // Live Search Filter
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            let count = 0;
            document.querySelectorAll('.issue-card').forEach(card => {
                const text = (card.dataset.title + ' ' + card.dataset.category + ' #' + card.dataset.id).toLowerCase();
                if (text.includes(query)) {
                    card.style.display = 'block';
                    count++;
                } else {
                    card.style.display = 'none';
                }
            });
            document.getElementById('activeCount').innerText = `Showing ${count}`;
        });

        async function runAnalysis() {
            const btn = document.getElementById('runBtn');
            const timer = document.getElementById('execTimer');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Analyzing via Supabase RAG + Gemini AI...</span>';
            timer.innerText = 'Processing...';

            const payload = {
                ticket_id: parseInt(currentIssueId),
                client_name: document.getElementById('clientName').value,
                client_email: document.getElementById('clientEmail').value,
                invoices_summary: document.getElementById('invoicesSummary').value,
                subject: document.getElementById('ticketSubject').value,
                message: document.getElementById('ticketMessage').value
            };

            const startTime = performance.now();

            try {
                const res = await fetch('{{ url('/test-issue') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                const duration = ((performance.now() - startTime) / 1000).toFixed(2);
                timer.innerText = `Completed in ${duration}s`;

                if (data.ticket && data.ticket.ai_analysis) {
                    const ai = data.ticket.ai_analysis;
                    document.getElementById('badgeDept').innerText = ai.department || 'N/A';
                    
                    const prioEl = document.getElementById('badgePriority');
                    prioEl.innerText = ai.priority || 'N/A';
                    if (ai.priority === 'Critical' || ai.priority === 'High') {
                        prioEl.className = 'text-xs font-bold text-rose-400 mt-0.5';
                    } else if (ai.priority === 'Medium') {
                        prioEl.className = 'text-xs font-bold text-amber-400 mt-0.5';
                    } else {
                        prioEl.className = 'text-xs font-bold text-emerald-400 mt-0.5';
                    }

                    document.getElementById('badgeMood').innerText = ai.customer_mood || 'Calm';
                    if (data.license && data.license.remaining_quota !== undefined) {
                        document.getElementById('badgeQuota').innerText = data.license.remaining_quota;
                    }

                    document.getElementById('outReasoning').innerText = ai.reasoning;
                    document.getElementById('outKbDoc').innerText = ai.matched_kb_article || 'None';
                    document.getElementById('outReply').innerText = ai.suggested_reply;

                    // WHMCS Staff note simulation
                    document.getElementById('whmcsStaffNote').innerText = 
`[AI Auto-Analysis & Verified KB Solution]
--------------------------------------------------
Ticket ID: #${payload.ticket_id} | Priority: ${ai.priority} | Department: ${ai.department}
Customer Mood: ${ai.customer_mood} | Invoices: ${payload.invoices_summary}
Matched Knowledge Base: ${ai.matched_kb_article || 'None'}

Reasoning:
${ai.reasoning}

Suggested Customer Reply (Internal Draft - Review & Send):
${ai.suggested_reply}`;
                } else {
                    document.getElementById('outReasoning').innerText = 'Analysis returned: ' + JSON.stringify(data);
                }
            } catch (err) {
                timer.innerText = 'Failed';
                document.getElementById('outReasoning').innerText = 'Error: ' + err.message;
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-play"></i><span>Run AI & Supabase RAG Analysis</span>';
            }
        }

        function copyReply() {
            const text = document.getElementById('outReply').innerText;
            navigator.clipboard.writeText(text);
            alert('Suggested reply copied to clipboard!');
        }

        // Auto select first card on load
        window.addEventListener('DOMContentLoaded', () => {
            const first = document.querySelector('.issue-card');
            if (first) selectIssue(first);
        });
    </script>

</body>
</html>

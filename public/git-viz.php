<?php
// /Users/narongsak/hrX/public/git-viz.php

$action = $_GET['action'] ?? 'view';

// 1. Return Git Commits Data
if ($action === 'data') {
    header('Content-Type: application/json');
    // Get all commits, ordered by date
    $output = shell_exec('git log --all --date-order --format="%H|%P|%an|%ad|%s|%d" --date=short');
    $lines = explode("\n", trim($output));
    
    $commits = [];
    foreach ($lines as $index => $line) {
        if (empty(trim($line))) continue;
        $parts = explode('|', $line, 6);
        
        $refs = trim($parts[5] ?? '');
        $isHead = strpos($refs, 'HEAD') !== false;
        
        $commits[] = [
            'hash' => $parts[0] ?? '',
            'parents' => isset($parts[1]) && $parts[1] !== '' ? explode(' ', $parts[1]) : [],
            'author' => $parts[2] ?? '',
            'date' => $parts[3] ?? '',
            'message' => $parts[4] ?? '',
            'refs' => $refs,
            'isHead' => $isHead,
            'level' => $index // Force chronological order top-to-bottom
        ];
    }
    echo json_encode($commits);
    exit;
}

// 2. Return Specific Commit Diff/Stat
if ($action === 'diff') {
    $hash = $_GET['hash'] ?? '';
    // Prevent command injection
    if (!preg_match('/^[a-f0-9]+$/', $hash)) {
        echo "Invalid hash";
        exit;
    }
    header('Content-Type: text/plain');
    echo shell_exec("git show --stat {$hash} 2>&1");
    echo "\n\n--- DETAILS ---\n\n";
    echo shell_exec("git show --patch {$hash} 2>&1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Git Branch Visualizer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <style>
        #mynetwork {
            width: 100%;
            height: 100%;
            background-color: #f8fafc;
        }
        .vis-tooltip {
            background-color: white !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 0.5rem !important;
            padding: 10px !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
            font-size: 0.875rem !important;
        }
    </style>
</head>
<body class="h-screen w-screen flex flex-col md:flex-row bg-slate-50 text-slate-800 font-sans overflow-hidden">

    <!-- Sidebar / Commit Detail Panel -->
    <div class="w-full md:w-1/3 h-1/2 md:h-full bg-white border-r border-slate-200 flex flex-col shadow-lg z-10">
        <div class="p-6 border-b border-slate-100 flex-none bg-indigo-600 text-white">
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" /></svg>
                Git Visualizer
            </h1>
            <p class="text-indigo-100 text-sm mt-1">Standalone interactive git tree</p>
        </div>
        
        <div id="panel-initial" class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-4 text-slate-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" /></svg>
            <p class="text-lg font-medium text-slate-500">คลิกที่ Node</p>
            <p class="text-sm">เพื่อดูรายละเอียดของแต่ละ Commit</p>
        </div>

        <div id="panel-content" class="flex-1 flex flex-col hidden overflow-hidden">
            <div class="p-6 bg-slate-50 border-b border-slate-200 flex-none">
                <div class="flex items-center gap-2 mb-2">
                    <span id="detail-hash" class="px-2 py-1 bg-indigo-100 text-indigo-700 font-mono text-xs rounded-md font-bold"></span>
                    <span id="detail-author" class="text-sm font-medium text-slate-600"></span>
                </div>
                <h2 id="detail-msg" class="text-xl font-bold text-slate-800 mb-2 leading-tight"></h2>
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <span id="detail-date"></span>
                </div>
                <div id="detail-refs" class="mt-3 flex flex-wrap gap-1"></div>
            </div>
            
            <div class="flex-1 overflow-y-auto bg-white p-4">
                <p class="text-xs font-bold text-slate-400 mb-2 uppercase tracking-wider">Changes & Diffs</p>
                <div id="detail-loading" class="text-sm text-slate-500 hidden">กำลังโหลด...</div>
                <pre id="detail-diff" class="text-[11px] font-mono text-slate-700 bg-slate-50 p-3 rounded-lg border border-slate-100 whitespace-pre-wrap overflow-x-auto"></pre>
            </div>
        </div>
    </div>

    <!-- Graph Container -->
    <div class="w-full md:w-2/3 h-1/2 md:h-full relative">
        <div id="mynetwork"></div>
        <div class="absolute top-4 right-4 bg-white/90 backdrop-blur px-4 py-2 rounded-full shadow border border-slate-200 text-xs font-medium text-slate-500 flex items-center gap-2 z-20">
            <span>💡 เลื่อนเพื่อซูม</span>
            <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
            <span>ลากเพื่อแพน</span>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            // Fetch graph data
            const res = await fetch('?action=data');
            const commits = await res.json();

            // Prepare nodes and edges
            const nodes = [];
            const edges = [];
            
            // Branch color logic
            const branchColors = ['#4f46e5', '#ec4899', '#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6'];
            let colorIndex = 0;
            const authorColors = {};

            commits.forEach((c) => {
                // Assign color by author or refs
                if (!authorColors[c.author]) {
                    authorColors[c.author] = branchColors[colorIndex % branchColors.length];
                    colorIndex++;
                }
                const color = authorColors[c.author];

                let label = c.message.length > 25 ? c.message.substring(0,25) + '...' : c.message;
                
                // Bold label if it's HEAD
                let font = { size: 12, color: '#334155' };
                let borderWidth = 2;
                if (c.isHead) {
                    font = { size: 14, color: '#000', bold: true };
                    borderWidth = 4;
                }

                nodes.push({
                    id: c.hash,
                    label: label,
                    title: `<b>${c.hash.substring(0,7)}</b><br/>${c.author}<br/>${c.date}<br/><i>${c.message}</i>${c.refs ? '<br/><br/><span style="color:#4f46e5">'+c.refs+'</span>' : ''}`,
                    level: c.level,
                    shape: 'dot',
                    size: c.isHead ? 16 : 12,
                    color: {
                        background: '#fff',
                        border: color,
                        highlight: { background: color, border: color }
                    },
                    font: font,
                    borderWidth: borderWidth,
                    commitData: c // store for click event
                });

                c.parents.forEach(p => {
                    edges.push({
                        from: c.hash,
                        to: p,
                        color: { color: '#cbd5e1', highlight: color },
                        arrows: { to: { enabled: true, scaleFactor: 0.5 } }
                    });
                });
            });

            // Create network
            const container = document.getElementById('mynetwork');
            const data = {
                nodes: new vis.DataSet(nodes),
                edges: new vis.DataSet(edges)
            };
            const options = {
                layout: {
                    hierarchical: {
                        direction: 'UD', // Up to Down
                        sortMethod: 'directed',
                        levelSeparation: 70,
                        nodeSpacing: 200,
                        treeSpacing: 200
                    }
                },
                physics: false,
                interaction: {
                    hover: true,
                    tooltipDelay: 100
                }
            };

            const network = new vis.Network(container, data, options);

            // Handle Click
            network.on('click', async function (params) {
                if (params.nodes.length > 0) {
                    const nodeId = params.nodes[0];
                    const node = data.nodes.get(nodeId);
                    const c = node.commitData;

                    // Update UI
                    document.getElementById('panel-initial').classList.add('hidden');
                    document.getElementById('panel-content').classList.remove('hidden');
                    
                    document.getElementById('detail-hash').innerText = c.hash.substring(0,7);
                    document.getElementById('detail-author').innerText = c.author;
                    document.getElementById('detail-msg').innerText = c.message;
                    document.getElementById('detail-date').innerText = c.date;
                    
                    // Refs badges
                    const refsContainer = document.getElementById('detail-refs');
                    refsContainer.innerHTML = '';
                    if (c.refs) {
                        let cleanRefs = c.refs.replace(/[()]/g, '');
                        cleanRefs.split(',').forEach(r => {
                            r = r.trim();
                            if(!r) return;
                            let span = document.createElement('span');
                            span.className = 'px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-100 text-emerald-700';
                            if(r.includes('HEAD')) span.className = 'px-2 py-0.5 text-[10px] font-bold rounded bg-purple-100 text-purple-700';
                            if(r.includes('origin')) span.className = 'px-2 py-0.5 text-[10px] font-bold rounded bg-blue-100 text-blue-700';
                            span.innerText = r;
                            refsContainer.appendChild(span);
                        });
                    }

                    // Fetch Diff
                    document.getElementById('detail-diff').innerText = '';
                    document.getElementById('detail-loading').classList.remove('hidden');
                    
                    const res = await fetch(`?action=diff&hash=${c.hash}`);
                    const diffText = await res.text();
                    
                    document.getElementById('detail-loading').classList.add('hidden');
                    document.getElementById('detail-diff').innerText = diffText;
                }
            });
            
            // Auto focus on first node
            if(nodes.length > 0) {
                network.focus(nodes[0].id, { scale: 1.0 });
            }
        });
    </script>
</body>
</html>

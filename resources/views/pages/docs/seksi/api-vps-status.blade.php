<section id="api-vps-status" class="scroll-mt-36 space-y-4">
    <div class="flex items-center gap-2">
        <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono-code bg-emerald-100 text-emerald-800 uppercase">GET</span>
        <span class="font-mono-code text-xs font-bold text-slate-900">/api/admin/vps/{id}/status</span>
    </div>
    <h3 class="text-xl font-bold text-slate-900 tracking-tight">
        Cek Status Realtime VPS Instance
    </h3>
    <p class="text-slate-600 text-sm leading-relaxed">
        Mengembalikan rincian teknis, spesifikasi hardware, status hypervisor running/stopped, IP publik, dan timestamp sinkronisasi terakhir.
    </p>

    <!-- Interactive Code Tabs (cURL, Python, Node.js, PHP) -->
    <div class="rounded-xl border border-slate-800 bg-slate-950 overflow-hidden text-xs"
         x-data="{ activeSnippet: 'curl' }">
        <div class="bg-slate-900 px-4 py-2 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button @click="activeSnippet = 'curl'"
                        :class="activeSnippet === 'curl' ? 'text-white border-b-2 border-[#4A6FA5] font-bold' : 'text-slate-400 hover:text-slate-200'"
                        class="px-2 py-1 transition-colors">cURL</button>
                <button @click="activeSnippet = 'python'"
                        :class="activeSnippet === 'python' ? 'text-white border-b-2 border-[#4A6FA5] font-bold' : 'text-slate-400 hover:text-slate-200'"
                        class="px-2 py-1 transition-colors">Python</button>
                <button @click="activeSnippet = 'node'"
                        :class="activeSnippet === 'node' ? 'text-white border-b-2 border-[#4A6FA5] font-bold' : 'text-slate-400 hover:text-slate-200'"
                        class="px-2 py-1 transition-colors">Node.js</button>
                <button @click="activeSnippet = 'php'"
                        :class="activeSnippet === 'php' ? 'text-white border-b-2 border-[#4A6FA5] font-bold' : 'text-slate-400 hover:text-slate-200'"
                        class="px-2 py-1 transition-colors">PHP</button>
            </div>
            <button @click="copyToClipboard(
                        activeSnippet === 'curl' ? 'curl -X GET https://cravacloud.id/api/admin/vps/1/status \\\n  -H \'X-Admin-Key: YOUR_ADMIN_KEY\' \\\n  -H \'Accept: application/json\'' :
                        activeSnippet === 'python' ? 'import requests\n\nurl = \'https://cravacloud.id/api/admin/vps/1/status\'\nheaders = {\'X-Admin-Key\': \'YOUR_ADMIN_KEY\', \'Accept\': \'application/json\'}\nresponse = requests.get(url, headers=headers)\nprint(response.json())' :
                        activeSnippet === 'node' ? 'const res = await fetch(\'https://cravacloud.id/api/admin/vps/1/status\', {\n  headers: { \'X-Admin-Key\': \'YOUR_ADMIN_KEY\', \'Accept\': \'application/json\' }\n});\nconsole.log(await res.json());' :
                        '<?php\n$ch = curl_init(\'https://cravacloud.id/api/admin/vps/1/status\');\ncurl_setopt($ch, CURLOPT_HTTPHEADER, [\'X-Admin-Key: YOUR_ADMIN_KEY\', \'Accept: application/json\']);\ncurl_setopt($ch, CURLOPT_RETURNTRANSFER, true);\necho curl_exec($ch);',
                        'api-vps-status-code')"
                    class="text-[11px] font-semibold text-slate-400 hover:text-white flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span x-text="copied['api-vps-status-code'] ? 'Tersalin!' : 'Salin'"></span>
            </button>
        </div>
        <div class="p-4 font-mono-code text-slate-300 overflow-x-auto leading-relaxed">
            <div x-show="activeSnippet === 'curl'">
                <p><span class="text-emerald-400">curl</span> -X GET https://cravacloud.id/api/admin/vps/1/status \</p>
                <p class="pl-4">-H <span class="text-amber-300">"X-Admin-Key: YOUR_ADMIN_KEY"</span> \</p>
                <p class="pl-4">-H <span class="text-amber-300">"Accept: application/json"</span></p>
            </div>
            <div x-show="activeSnippet === 'python'" style="display:none;">
                <p><span class="text-purple-400">import</span> requests</p>
                <br>
                <p>url = <span class="text-amber-300">'https://cravacloud.id/api/admin/vps/1/status'</span></p>
                <p>headers = {<span class="text-amber-300">'X-Admin-Key'</span>: <span class="text-amber-300">'YOUR_ADMIN_KEY'</span>, <span class="text-amber-300">'Accept'</span>: <span class="text-amber-300">'application/json'</span>}</p>
                <p>response = requests.get(url, headers=headers)</p>
                <p>print(response.json())</p>
            </div>
            <div x-show="activeSnippet === 'node'" style="display:none;">
                <p><span class="text-purple-400">const</span> res = <span class="text-purple-400">await</span> fetch(<span class="text-amber-300">'https://cravacloud.id/api/admin/vps/1/status'</span>, {</p>
                <p class="pl-4">headers: {</p>
                <p class="pl-8"><span class="text-amber-300">'X-Admin-Key'</span>: <span class="text-amber-300">'YOUR_ADMIN_KEY'</span>,</p>
                <p class="pl-8"><span class="text-amber-300">'Accept'</span>: <span class="text-amber-300">'application/json'</span></p>
                <p class="pl-4">}</p>
                <p>});</p>
                <p>console.log(<span class="text-purple-400">await</span> res.json());</p>
            </div>
            <div x-show="activeSnippet === 'php'" style="display:none;">
                <p>&lt;?php</p>
                <p>$ch = curl_init(<span class="text-amber-300">'https://cravacloud.id/api/admin/vps/1/status'</span>);</p>
                <p>curl_setopt($ch, CURLOPT_HTTPHEADER, [</p>
                <p class="pl-4"><span class="text-amber-300">'X-Admin-Key: YOUR_ADMIN_KEY'</span>,</p>
                <p class="pl-4"><span class="text-amber-300">'Accept: application/json'</span></p>
                <p>]);</p>
                <p>curl_setopt($ch, CURLOPT_RETURNTRANSFER, <span class="text-amber-300">true</span>);</p>
                <p>$response = curl_exec($ch);</p>
                <p>echo $response;</p>
            </div>
        </div>
    </div>

    <!-- JSON Response Sample -->
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 font-mono-code text-xs">
        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-2 font-sans">Contoh Payload Respon 200 OK:</span>
        <pre class="text-slate-800 overflow-x-auto">{
"id": 1,
"hostname": "srv-production-01",
"public_ip": "103.152.118.42",
"status": "running",
"cpu": 4,
"ram": 8,
"disk": 80,
"os": "Ubuntu 24.04 LTS",
"control_panel": "coolify",
"billing_cycle": "monthly",
"last_check": "2026-09-14T15:30:00.000000Z"
}</pre>
    </div>
</section>

@extends('layouts.app', ['title' => 'Database'])

@section('page-title', 'Database')
@section('page-description', 'The live structure: '.count($schema['tables']).' tables in '.$schema['database'].' ('.$schema['driver'].'), read '.$schema['generated'].'.')

@section('page-actions')
    <x-ui.button :href="route('developer.index')" variant="secondary" icon="terminal">Developer panel</x-ui.button>
    <x-ui.button :href="route('developer.guide')" variant="secondary" icon="code">Developer guide</x-ui.button>
@endsection

@section('content')
    <script type="application/json" id="db-data">@json($schema)</script>

    <div id="db-app" class="flex flex-col gap-3">
        <section class="flex flex-wrap items-end gap-x-3 gap-y-2 rounded-lg border border-pedago-line bg-white px-4 py-3" aria-label="Filters">
            <div class="w-full sm:w-64">
                <label class="mb-1 block text-xs font-medium text-pedago-muted" for="db-search">Search tables and columns</label>
                <input class="h-9 w-full rounded-md border border-pedago-line px-2.5 text-sm" id="db-search" type="search" placeholder="e.g. lead, owner, status">
            </div>
            <fieldset class="min-w-0">
                <legend class="mb-1 text-xs font-medium text-pedago-muted">Modules</legend>
                <div class="flex flex-wrap gap-1.5" id="db-modules"></div>
            </fieldset>
            <div class="ml-auto flex flex-wrap items-center gap-1.5" id="db-actions">
                <button class="inline-flex h-9 items-center rounded-md border border-pedago-line px-3 text-sm font-semibold transition-colors duration-150 hover:bg-pedago-tint" type="button" id="db-export-svg">Download SVG</button>
                <button class="inline-flex h-9 items-center rounded-md border border-pedago-line px-3 text-sm font-semibold transition-colors duration-150 hover:bg-pedago-tint" type="button" id="db-export-mermaid">Copy Mermaid</button>
                <button class="inline-flex h-9 items-center rounded-md border border-pedago-line px-3 text-sm font-semibold transition-colors duration-150 hover:bg-pedago-tint" type="button" id="db-export-json">Download JSON</button>
            </div>
        </section>

        <div class="flex gap-1 border-b border-pedago-line" role="tablist" aria-label="Database views" id="db-tabs">
            @foreach (['diagram' => 'ER diagram', 'tables' => 'Tables', 'mapping' => 'Mapping', 'relations' => 'Relationships'] as $key => $label)
                <button class="-mb-px inline-flex min-h-11 items-center border-b-2 border-transparent px-3 text-sm font-semibold text-pedago-muted transition-colors duration-150 hover:text-pedago-ink aria-selected:border-pedago-blue aria-selected:text-pedago-blue-dark" type="button" role="tab" id="tab-{{ $key }}" data-tab="{{ $key }}" aria-controls="panel-{{ $key }}" aria-selected="{{ $key === 'diagram' ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
            <span class="num ml-auto self-center pr-1 text-xs text-pedago-muted" id="db-count" aria-live="polite"></span>
        </div>

        <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
            <div class="min-w-0">
                <section id="panel-diagram" role="tabpanel" aria-labelledby="tab-diagram">
                    <div class="relative h-[68vh] min-h-[420px] overflow-hidden rounded-lg border border-pedago-line bg-pedago-row" id="er-stage">
                        <svg class="size-full touch-none select-none" id="er-svg" role="img" aria-label="Entity relationship diagram. The Tables tab lists the same information as text." xmlns="http://www.w3.org/2000/svg">
                            <g id="er-viewport"><g id="er-edges"></g><g id="er-nodes"></g></g>
                        </svg>
                        <div class="absolute right-3 top-3 flex flex-col gap-1.5">
                            <button class="inline-flex size-9 items-center justify-center rounded-md border border-pedago-line bg-white text-lg font-semibold transition-colors duration-150 hover:bg-pedago-tint" type="button" id="er-zoom-in" aria-label="Zoom in">+</button>
                            <button class="inline-flex size-9 items-center justify-center rounded-md border border-pedago-line bg-white text-lg font-semibold transition-colors duration-150 hover:bg-pedago-tint" type="button" id="er-zoom-out" aria-label="Zoom out">−</button>
                            <button class="inline-flex size-9 items-center justify-center rounded-md border border-pedago-line bg-white text-xs font-semibold transition-colors duration-150 hover:bg-pedago-tint" type="button" id="er-fit" aria-label="Fit diagram to screen">Fit</button>
                        </div>
                        <div class="absolute bottom-3 left-3 flex flex-wrap items-center gap-2 rounded-md border border-pedago-line bg-white/95 px-3 py-1.5 text-xs">
                            <label class="inline-flex cursor-pointer items-center gap-1.5"><input class="size-4 accent-pedago-blue" type="checkbox" id="er-all-columns"> All columns</label>
                            <span class="text-pedago-muted" aria-hidden="true">|</span>
                            <span class="text-pedago-muted">Drag to pan · scroll to zoom · drag a table to move it · click to select</span>
                        </div>
                        <button class="absolute bottom-3 right-3 hidden h-8 items-center rounded-md border border-pedago-line bg-white px-3 text-xs font-semibold transition-colors duration-150 hover:bg-pedago-tint" type="button" id="er-clear">Clear selection</button>
                    </div>
                    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-pedago-muted" id="er-legend" aria-label="Legend"></ul>
                </section>

                <section id="panel-tables" role="tabpanel" aria-labelledby="tab-tables" hidden>
                    <div class="overflow-x-auto rounded-lg border border-pedago-line bg-white">
                        <table class="w-full min-w-[760px] border-collapse text-left" id="tables-table">
                            <thead>
                                <tr class="border-b border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                                    @foreach (['name' => 'Table', 'module' => 'Module', 'model' => 'Model', 'rows' => 'Rows', 'columns' => 'Columns', 'out' => 'Points to', 'in' => 'Used by'] as $key => $label)
                                        <th class="px-3 py-2 font-semibold" scope="col" aria-sort="none" data-sort="{{ $key }}"><button class="inline-flex items-center gap-1 uppercase tracking-wide" type="button">{{ $label }}<span aria-hidden="true" class="text-pedago-blue"></span></button></th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="text-[13px]" id="tables-body"></tbody>
                        </table>
                    </div>
                </section>

                <section id="panel-mapping" role="tabpanel" aria-labelledby="tab-mapping" hidden>
                    <p class="mb-2 text-xs text-pedago-muted">How the database connects to the code: each table, the Eloquent model that owns it, the relations that model declares, and the controllers and actions that read or write it.</p>
                    <div class="overflow-x-auto rounded-lg border border-pedago-line bg-white">
                        <table class="w-full min-w-[900px] border-collapse text-left">
                            <thead>
                                <tr class="border-b border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                                    <th class="px-3 py-2 font-semibold" scope="col">Table</th>
                                    <th class="px-3 py-2 font-semibold" scope="col">Model</th>
                                    <th class="px-3 py-2 font-semibold" scope="col">Relations</th>
                                    <th class="px-3 py-2 font-semibold" scope="col">Code that uses it</th>
                                </tr>
                            </thead>
                            <tbody class="text-[13px]" id="mapping-body"></tbody>
                        </table>
                    </div>
                </section>

                <section id="panel-relations" role="tabpanel" aria-labelledby="tab-relations" hidden>
                    <div class="overflow-x-auto rounded-lg border border-pedago-line bg-white">
                        <table class="w-full min-w-[820px] border-collapse text-left">
                            <thead>
                                <tr class="border-b border-pedago-line bg-pedago-canvas text-[11.5px] uppercase tracking-wide text-pedago-muted">
                                    <th class="px-3 py-2 font-semibold" scope="col">Child column</th>
                                    <th class="px-3 py-2 font-semibold" scope="col">Parent column</th>
                                    <th class="px-3 py-2 font-semibold" scope="col">Cardinality</th>
                                    <th class="px-3 py-2 font-semibold" scope="col">On delete</th>
                                    <th class="px-3 py-2 font-semibold" scope="col">On update</th>
                                </tr>
                            </thead>
                            <tbody class="text-[13px]" id="relations-body"></tbody>
                        </table>
                    </div>
                </section>
            </div>

            <aside class="rounded-lg border border-pedago-line bg-white xl:sticky xl:top-20 xl:max-h-[calc(100vh-6rem)] xl:overflow-y-auto" id="db-detail" aria-label="Table details" aria-live="polite">
                <p class="px-4 py-6 text-center text-sm text-pedago-muted">Select a table in the diagram or the lists to see its columns, keys, model and the code that uses it.</p>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            'use strict';

            const data = JSON.parse(document.getElementById('db-data').textContent);
            const tables = data.tables;
            const byName = new Map(tables.map((table) => [table.name, table]));
            const COLORS = {
                'People': '#233f96', 'Catalogue': '#7a4f00', 'Leads and distribution': '#c92e18',
                'Enrollment and finance': '#0f6845', 'System': '#55617d', 'Framework': '#8c9ac8', 'Other': '#14203f',
            };
            const NODE_W = 232, HEAD_H = 30, ROW_H = 19, PAD = 8, MAX_COL_H = 1100, STACK_GAP = 26, COL_GAP = 96;
            const state = { search: '', modules: new Set(data.modules.filter((module) => module !== 'Framework')), selected: null, allColumns: false, tab: 'diagram', sort: { key: 'name', dir: 1 } };
            const pos = new Map();
            let view = { x: 20, y: 20, k: 0.8 };

            const el = (id) => document.getElementById(id);
            const NS = 'http://www.w3.org/2000/svg';
            const svgEl = (name, attrs = {}, text) => {
                const node = document.createElementNS(NS, name);
                Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
                if (text !== undefined) node.textContent = text;
                return node;
            };
            const html = (tag, attrs = {}, children = []) => {
                const node = document.createElement(tag);
                Object.entries(attrs).forEach(([key, value]) => {
                    if (key === 'class') node.className = value; else if (key === 'text') node.textContent = value; else node.setAttribute(key, value);
                });
                [].concat(children).forEach((child) => node.append(child));
                return node;
            };
            const fmt = (value) => value === null || value === undefined ? '—' : Number(value).toLocaleString();
            const matches = (table) => {
                const term = state.search.trim().toLowerCase();
                if (!term) return true;
                return table.name.includes(term) || table.module.toLowerCase().includes(term) || (table.model && table.model.name.toLowerCase().includes(term))
                    || table.purpose.toLowerCase().includes(term) || table.columns.some((column) => column.name.includes(term));
            };
            const visible = (table) => state.modules.has(table.module);
            const shown = () => tables.filter(visible);
            const connected = (name) => {
                const set = new Set([name]);
                const table = byName.get(name);
                table.foreign_keys.forEach((key) => set.add(key.table));
                table.referenced_by.forEach((ref) => set.add(ref.table));
                return set;
            };

            // ---------------- layout ----------------
            const columnsShown = (table) => state.allColumns ? table.columns : table.columns.filter((column) => column.pk || column.fk);
            const nodeHeight = (table) => HEAD_H + Math.max(1, columnsShown(table).length) * ROW_H + PAD + (state.allColumns || columnsShown(table).length === table.columns.length ? 0 : ROW_H);

            const autoLayout = () => {
                let x = 0;
                data.modules.forEach((module) => {
                    const list = tables.filter((table) => table.module === module);
                    if (!list.length) return;
                    let y = 0, colX = x, started = false;
                    list.forEach((table) => {
                        const h = nodeHeight(table);
                        if (started && y + h > MAX_COL_H) { colX += NODE_W + 40; y = 0; }
                        pos.set(table.name, { x: colX, y });
                        y += h + STACK_GAP;
                        started = true;
                    });
                    x = colX + NODE_W + COL_GAP;
                });
            };

            // ---------------- diagram ----------------
            const edgeAnchor = (table, column, side) => {
                const p = pos.get(table.name);
                const cols = columnsShown(table);
                const index = cols.findIndex((item) => item.name === column);
                const y = p.y + HEAD_H + (index >= 0 ? index : 0) * ROW_H + ROW_H / 2;
                return { x: side === 'right' ? p.x + NODE_W : p.x, y: index >= 0 ? y : p.y + HEAD_H / 2 };
            };
            const edgePath = (child, parent, key) => {
                const c = pos.get(child.name), p = pos.get(parent.name);
                let childSide, parentSide;
                if (c.x + NODE_W + 20 < p.x) { childSide = 'right'; parentSide = 'left'; }
                else if (p.x + NODE_W + 20 < c.x) { childSide = 'left'; parentSide = 'right'; }
                else { childSide = 'right'; parentSide = 'right'; }
                const a = edgeAnchor(child, key.columns[0], childSide);
                const b = edgeAnchor(parent, key.foreign_columns[0], parentSide);
                const out = childSide === parentSide ? 70 : Math.max(40, Math.abs(b.x - a.x) / 2);
                const d1 = childSide === 'right' ? out : -out;
                const d2 = parentSide === 'right' ? (childSide === parentSide ? out : out) : -out;
                return { a, b, childSide, parentSide, d: `M ${a.x} ${a.y} C ${a.x + d1} ${a.y}, ${b.x + (parentSide === 'left' ? -out : out)} ${b.y}, ${b.x} ${b.y}` };
            };

            const drawEdges = () => {
                const layer = el('er-edges');
                layer.replaceChildren();
                const focus = state.selected ? connected(state.selected) : null;
                shown().forEach((child) => child.foreign_keys.forEach((key) => {
                    const parent = byName.get(key.table);
                    if (!parent || !visible(parent)) return;
                    const { a, b, childSide, parentSide, d } = edgePath(child, parent, key);
                    const active = state.selected && (child.name === state.selected || parent.name === state.selected);
                    const dim = state.selected && !active;
                    const group = svgEl('g', { opacity: dim ? '0.12' : '1' });
                    const title = svgEl('title', {}, `${child.name}.${key.columns.join(', ')} → ${parent.name}.${key.foreign_columns.join(', ')} (on delete ${key.on_delete}, on update ${key.on_update})`);
                    group.append(title);
                    group.append(svgEl('path', { d, fill: 'none', stroke: active ? '#233f96' : '#8c9ac8', 'stroke-width': active ? '2.2' : '1.3' }));
                    const end = (point, side, label, color) => {
                        const dx = side === 'right' ? 10 : -10;
                        group.append(svgEl('text', { x: point.x + dx, y: point.y - 5, 'font-size': '10', 'font-family': 'IBM Plex Mono, monospace', 'text-anchor': 'middle', fill: color, 'font-weight': '600' }, label));
                    };
                    end(a, childSide, 'N', active ? '#233f96' : '#55617d');
                    end(b, parentSide, '1', active ? '#233f96' : '#55617d');
                    layer.append(group);
                }));
            };

            const drawNodes = () => {
                const layer = el('er-nodes');
                layer.replaceChildren();
                const focus = state.selected ? connected(state.selected) : null;
                shown().forEach((table) => {
                    const p = pos.get(table.name);
                    const cols = columnsShown(table);
                    const hidden = table.columns.length - cols.length;
                    const h = nodeHeight(table);
                    const match = matches(table);
                    const dim = (state.selected && !focus.has(table.name)) || (state.search.trim() && !match);
                    const selected = state.selected === table.name;
                    const group = svgEl('g', { transform: `translate(${p.x} ${p.y})`, 'data-table': table.name, tabindex: '0', role: 'button', 'aria-label': `${table.name}, ${table.columns.length} columns`, opacity: dim ? '0.22' : '1', style: 'cursor: grab; outline: none' });
                    group.append(svgEl('rect', { width: NODE_W, height: h, rx: '8', fill: '#ffffff', stroke: selected ? '#233f96' : '#dce1ee', 'stroke-width': selected ? '2.5' : '1.2' }));
                    group.append(svgEl('path', { d: `M 0 ${HEAD_H} V 8 a 8 8 0 0 1 8 -8 H ${NODE_W - 8} a 8 8 0 0 1 8 8 V ${HEAD_H} Z`, fill: COLORS[table.module] || '#14203f' }));
                    group.append(svgEl('text', { x: 10, y: 20, 'font-size': '13', 'font-weight': '700', fill: '#ffffff', 'font-family': 'IBM Plex Sans, sans-serif' }, table.name));
                    group.append(svgEl('text', { x: NODE_W - 10, y: 20, 'font-size': '10', fill: '#ffffff', 'text-anchor': 'end', 'font-family': 'IBM Plex Mono, monospace', opacity: '0.85' }, fmt(table.rows)));
                    cols.forEach((column, index) => {
                        const y = HEAD_H + index * ROW_H + 14;
                        const badge = column.pk ? 'PK' : column.fk ? 'FK' : column.unique ? 'UQ' : '';
                        if (badge) group.append(svgEl('text', { x: 10, y, 'font-size': '9', 'font-weight': '700', fill: column.pk ? '#c92e18' : '#233f96', 'font-family': 'IBM Plex Mono, monospace' }, badge));
                        const highlight = state.search.trim() && column.name.includes(state.search.trim().toLowerCase());
                        group.append(svgEl('text', { x: 32, y, 'font-size': '11.5', fill: highlight ? '#c92e18' : '#14203f', 'font-weight': highlight || column.pk ? '600' : '400', 'font-family': 'IBM Plex Sans, sans-serif' }, column.name));
                        group.append(svgEl('text', { x: NODE_W - 10, y, 'font-size': '10', fill: '#55617d', 'text-anchor': 'end', 'font-family': 'IBM Plex Mono, monospace' }, column.type.length > 16 ? column.type.slice(0, 15) + '…' : column.type));
                    });
                    if (hidden > 0 && !state.allColumns) {
                        group.append(svgEl('text', { x: 32, y: HEAD_H + cols.length * ROW_H + 14, 'font-size': '10.5', fill: '#55617d', 'font-style': 'italic', 'font-family': 'IBM Plex Sans, sans-serif' }, `+ ${hidden} more columns`));
                    }
                    layer.append(group);
                });
            };

            const applyView = () => el('er-viewport').setAttribute('transform', `translate(${view.x} ${view.y}) scale(${view.k})`);
            const redraw = () => { drawEdges(); drawNodes(); applyView(); };

            const fit = () => {
                const list = shown();
                if (!list.length) return;
                const box = el('er-stage').getBoundingClientRect();
                if (box.width === 0) return;
                let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
                list.forEach((table) => { const p = pos.get(table.name); minX = Math.min(minX, p.x); minY = Math.min(minY, p.y); maxX = Math.max(maxX, p.x + NODE_W); maxY = Math.max(maxY, p.y + nodeHeight(table)); });
                const k = Math.min(1.1, Math.max(0.12, Math.min((box.width - 40) / (maxX - minX), (box.height - 70) / (maxY - minY))));
                view = { k, x: (box.width - (maxX - minX) * k) / 2 - minX * k, y: 20 - minY * k + Math.max(0, (box.height - 70 - (maxY - minY) * k) / 2) };
                applyView();
            };
            const centreOn = (name) => {
                const p = pos.get(name);
                if (!p) return;
                const box = el('er-stage').getBoundingClientRect();
                view.k = Math.max(view.k, 0.9);
                view.x = box.width / 2 - (p.x + NODE_W / 2) * view.k;
                view.y = box.height / 2 - (p.y + nodeHeight(byName.get(name)) / 2) * view.k;
                applyView();
            };
            const zoomAt = (factor, cx, cy) => {
                const next = Math.min(2.5, Math.max(0.1, view.k * factor));
                view.x = cx - (cx - view.x) * (next / view.k);
                view.y = cy - (cy - view.y) * (next / view.k);
                view.k = next;
                applyView();
            };

            // pan, drag, select
            const stage = el('er-stage'), svg = el('er-svg');
            let drag = null;
            svg.addEventListener('pointerdown', (event) => {
                const node = event.target.closest('[data-table]');
                const rect = stage.getBoundingClientRect();
                drag = { mode: node ? 'node' : 'pan', name: node ? node.dataset.table : null, sx: event.clientX, sy: event.clientY, moved: false, vx: view.x, vy: view.y, origin: node ? { ...pos.get(node.dataset.table) } : null, rect };
                svg.setPointerCapture(event.pointerId);
            });
            svg.addEventListener('pointermove', (event) => {
                if (!drag) return;
                const dx = event.clientX - drag.sx, dy = event.clientY - drag.sy;
                if (Math.abs(dx) + Math.abs(dy) > 3) drag.moved = true;
                if (!drag.moved) return;
                if (drag.mode === 'pan') { view.x = drag.vx + dx; view.y = drag.vy + dy; applyView(); }
                else { pos.set(drag.name, { x: drag.origin.x + dx / view.k, y: drag.origin.y + dy / view.k }); redraw(); }
            });
            svg.addEventListener('pointerup', (event) => {
                if (!drag) return;
                const wasClick = !drag.moved;
                const name = drag.name;
                drag = null;
                if (wasClick) select(name || null);
            });
            svg.addEventListener('wheel', (event) => {
                event.preventDefault();
                const rect = stage.getBoundingClientRect();
                zoomAt(event.deltaY < 0 ? 1.12 : 1 / 1.12, event.clientX - rect.left, event.clientY - rect.top);
            }, { passive: false });
            svg.addEventListener('keydown', (event) => {
                const node = event.target.closest('[data-table]');
                if (node && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); select(node.dataset.table); }
            });
            el('er-zoom-in').addEventListener('click', () => { const r = stage.getBoundingClientRect(); zoomAt(1.25, r.width / 2, r.height / 2); });
            el('er-zoom-out').addEventListener('click', () => { const r = stage.getBoundingClientRect(); zoomAt(0.8, r.width / 2, r.height / 2); });
            el('er-fit').addEventListener('click', fit);
            el('er-clear').addEventListener('click', () => select(null));
            el('er-all-columns').addEventListener('change', (event) => { state.allColumns = event.target.checked; autoLayout(); redraw(); fit(); });

            // ---------------- detail panel ----------------
            const badge = (text, tone) => html('span', { class: `inline-flex items-center rounded px-1.5 py-0.5 text-[10.5px] font-semibold ${tone}`, text });
            const tableLink = (name) => html('button', { class: 'font-semibold text-pedago-blue-dark underline underline-offset-2 hover:text-pedago-blue', type: 'button', 'data-goto': name, text: name });
            const renderDetail = () => {
                const panel = el('db-detail');
                panel.replaceChildren();
                if (!state.selected) {
                    panel.append(html('p', { class: 'px-4 py-6 text-center text-sm text-pedago-muted', text: 'Select a table in the diagram or the lists to see its columns, keys, model and the code that uses it.' }));
                    return;
                }
                const table = byName.get(state.selected);
                const head = html('div', { class: 'border-b border-pedago-line px-4 py-3' }, [
                    html('div', { class: 'flex items-start justify-between gap-2' }, [
                        html('h2', { class: 'num break-all text-base font-semibold', text: table.name }),
                        html('button', { class: 'inline-flex size-8 shrink-0 items-center justify-center rounded-md text-pedago-muted hover:bg-pedago-canvas', type: 'button', 'data-close': '1', 'aria-label': 'Close details', text: '✕' }),
                    ]),
                    html('p', { class: 'mt-0.5 text-xs text-pedago-muted', text: table.purpose || 'No description yet.' }),
                    html('div', { class: 'mt-2 flex flex-wrap gap-1.5' }, [
                        html('span', { class: 'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold text-white', style: `background:${COLORS[table.module] || '#14203f'}`, text: table.module }),
                        badge(`${fmt(table.rows)} rows`, 'bg-pedago-canvas text-pedago-ink'),
                        badge(`${table.columns.length} columns`, 'bg-pedago-canvas text-pedago-ink'),
                        badge(`${table.indexes.length} indexes`, 'bg-pedago-canvas text-pedago-ink'),
                    ]),
                    html('div', { class: 'mt-3 flex flex-wrap gap-1.5' }, [
                        html('button', { class: 'inline-flex h-8 items-center rounded-md border border-pedago-line px-2.5 text-xs font-semibold hover:bg-pedago-tint', type: 'button', 'data-show': table.name, text: 'Show in diagram' }),
                        html('button', { class: 'inline-flex h-8 items-center rounded-md border border-pedago-line px-2.5 text-xs font-semibold hover:bg-pedago-tint', type: 'button', 'data-copy-table': table.name, text: 'Copy Mermaid' }),
                    ]),
                ]);
                panel.append(head);

                const section = (title, body) => html('section', { class: 'border-b border-pedago-line px-4 py-3 last:border-0' }, [html('h3', { class: 'mb-1.5 text-[11.5px] font-semibold uppercase tracking-wider text-pedago-muted', text: title }), body]);

                const cols = html('ul', { class: 'divide-y divide-pedago-line text-[12.5px]' }, table.columns.map((column) => html('li', { class: 'flex flex-wrap items-center gap-x-2 gap-y-0.5 py-1.5' }, [
                    html('span', { class: 'num font-medium', text: column.name }),
                    html('span', { class: 'num text-xs text-pedago-muted', text: column.type }),
                    column.pk ? badge('PK', 'bg-pedago-red-tint text-pedago-red') : '',
                    column.fk ? badge('FK', 'bg-pedago-tint text-pedago-blue-dark') : '',
                    column.unique ? badge('UNIQUE', 'bg-pedago-green-tint text-pedago-green') : '',
                    column.nullable ? badge('NULL', 'bg-pedago-slate-tint text-pedago-muted') : '',
                    column.default !== null && column.default !== undefined ? html('span', { class: 'num text-[11px] text-pedago-muted', text: `default ${String(column.default).slice(0, 30)}` }) : '',
                ])));
                panel.append(section('Columns', cols));

                const out = table.foreign_keys.length
                    ? html('ul', { class: 'flex flex-col gap-1 text-[12.5px]' }, table.foreign_keys.map((key) => html('li', {}, [
                        html('span', { class: 'num', text: key.columns.join(', ') + ' → ' }), tableLink(key.table), html('span', { class: 'num text-pedago-muted', text: '.' + key.foreign_columns.join(', ') }),
                        html('span', { class: 'block text-[11px] text-pedago-muted', text: `on delete ${key.on_delete}, on update ${key.on_update}` }),
                    ])))
                    : html('p', { class: 'text-[12.5px] text-pedago-muted', text: 'This table points to no other table.' });
                panel.append(section('Points to (foreign keys)', out));

                const incoming = table.referenced_by.length
                    ? html('ul', { class: 'flex flex-col gap-1 text-[12.5px]' }, table.referenced_by.map((ref) => html('li', {}, [tableLink(ref.table), html('span', { class: 'num text-pedago-muted', text: '.' + ref.columns.join(', ') })])))
                    : html('p', { class: 'text-[12.5px] text-pedago-muted', text: 'No table points to this one.' });
                panel.append(section('Referenced by', incoming));

                const idx = table.indexes.length
                    ? html('ul', { class: 'flex flex-col gap-1 text-[12.5px]' }, table.indexes.map((index) => html('li', {}, [
                        html('span', { class: 'num font-medium', text: index.name }), ' ',
                        index.primary ? badge('PRIMARY', 'bg-pedago-red-tint text-pedago-red') : index.unique ? badge('UNIQUE', 'bg-pedago-green-tint text-pedago-green') : badge('INDEX', 'bg-pedago-canvas text-pedago-ink'),
                        html('span', { class: 'num block text-[11px] text-pedago-muted', text: index.columns.join(', ') }),
                    ])))
                    : html('p', { class: 'text-[12.5px] text-pedago-muted', text: 'No indexes.' });
                panel.append(section('Indexes', idx));

                const model = table.model
                    ? html('div', { class: 'text-[12.5px]' }, [
                        html('p', {}, [html('span', { class: 'num font-semibold', text: table.model.name }), html('span', { class: 'num block text-[11px] text-pedago-muted', text: table.model.file })]),
                        table.model.relations.length ? html('ul', { class: 'mt-1.5 flex flex-col gap-0.5' }, table.model.relations.map((relation) => html('li', { class: 'num text-[12px]' }, [`${relation.name}()`, html('span', { class: 'text-pedago-muted', text: `  ${relation.type} ${relation.related}` })]))) : '',
                    ])
                    : html('p', { class: 'text-[12.5px] text-pedago-muted', text: 'No Eloquent model (framework or pivot table).' });
                panel.append(section('Eloquent model and relations', model));

                const used = table.used_by.length
                    ? html('ul', { class: 'flex flex-wrap gap-1.5' }, table.used_by.map((name) => html('li', { class: 'num rounded bg-pedago-canvas px-1.5 py-0.5 text-[11.5px]', text: name })))
                    : html('p', { class: 'text-[12.5px] text-pedago-muted', text: 'No controller or action refers to its model directly.' });
                panel.append(section('Controllers and actions', used));
            };

            const select = (name) => {
                state.selected = name;
                el('er-clear').classList.toggle('hidden', !name);
                el('er-clear').classList.toggle('inline-flex', !!name);
                redraw();
                renderDetail();
                renderLists();
            };

            el('db-detail').addEventListener('click', (event) => {
                const goto = event.target.closest('[data-goto]');
                const show = event.target.closest('[data-show]');
                const copy = event.target.closest('[data-copy-table]');
                if (event.target.closest('[data-close]')) return select(null);
                if (goto) { const target = byName.get(goto.dataset.goto); if (target && !state.modules.has(target.module)) { state.modules.add(target.module); renderModules(); redraw(); } select(goto.dataset.goto); if (state.tab === 'diagram') centreOn(goto.dataset.goto); }
                if (show) { setTab('diagram'); centreOn(show.dataset.show); }
                if (copy) copyText(mermaid([byName.get(copy.dataset.copyTable)]), 'Mermaid for this table copied');
            });

            // ---------------- lists ----------------
            const rowClick = (name) => (event) => { if (!event.target.closest('a,button[data-goto]')) select(name); };
            const renderLists = () => {
                const list = shown().filter(matches);
                el('db-count').textContent = `${list.length} of ${tables.length} tables`;

                // tables
                const sorters = {
                    name: (t) => t.name, module: (t) => t.module, model: (t) => t.model ? t.model.name : '', rows: (t) => t.rows ?? -1,
                    columns: (t) => t.columns.length, out: (t) => t.foreign_keys.length, in: (t) => t.referenced_by.length,
                };
                const sorted = [...list].sort((a, b) => { const x = sorters[state.sort.key](a), y = sorters[state.sort.key](b); return (x > y ? 1 : x < y ? -1 : 0) * state.sort.dir; });
                document.querySelectorAll('#tables-table th[data-sort]').forEach((th) => {
                    const active = th.dataset.sort === state.sort.key;
                    th.setAttribute('aria-sort', active ? (state.sort.dir === 1 ? 'ascending' : 'descending') : 'none');
                    th.querySelector('span').textContent = active ? (state.sort.dir === 1 ? '▲' : '▼') : '';
                });
                const body = el('tables-body');
                body.replaceChildren(...sorted.map((table) => {
                    const row = html('tr', { class: `cursor-pointer border-b border-pedago-line transition-colors duration-150 hover:bg-pedago-row ${state.selected === table.name ? 'bg-pedago-tint' : ''}` }, [
                        html('th', { class: 'num px-3 py-2 font-medium', scope: 'row', text: table.name }),
                        html('td', { class: 'px-3 py-2' }, [html('span', { class: 'inline-flex items-center gap-1.5 text-xs' }, [html('i', { class: 'inline-block size-2 rounded-full', style: `background:${COLORS[table.module]}` }), table.module])]),
                        html('td', { class: 'num px-3 py-2 text-xs', text: table.model ? table.model.name : '—' }),
                        html('td', { class: 'num px-3 py-2 text-right text-xs', text: fmt(table.rows) }),
                        html('td', { class: 'num px-3 py-2 text-right text-xs', text: String(table.columns.length) }),
                        html('td', { class: 'num px-3 py-2 text-right text-xs', text: String(table.foreign_keys.length) }),
                        html('td', { class: 'num px-3 py-2 text-right text-xs', text: String(table.referenced_by.length) }),
                    ]);
                    row.addEventListener('click', rowClick(table.name));
                    return row;
                }));

                // mapping
                el('mapping-body').replaceChildren(...list.map((table) => {
                    const row = html('tr', { class: 'cursor-pointer border-b border-pedago-line align-top transition-colors duration-150 hover:bg-pedago-row' }, [
                        html('th', { class: 'num px-3 py-2 font-medium', scope: 'row', text: table.name }),
                        html('td', { class: 'px-3 py-2' }, table.model ? [html('span', { class: 'num text-xs font-semibold', text: table.model.name }), html('span', { class: 'num block text-[11px] text-pedago-muted', text: table.model.file })] : [html('span', { class: 'text-xs text-pedago-muted', text: '—' })]),
                        html('td', { class: 'num px-3 py-2 text-[11.5px] leading-5', text: table.model && table.model.relations.length ? table.model.relations.map((r) => `${r.name}() ${r.type} ${r.related}`).join('\n') : '—', style: 'white-space: pre-line' }),
                        html('td', { class: 'px-3 py-2' }, [html('span', { class: 'num text-[11.5px] leading-5', text: table.used_by.length ? table.used_by.join(', ') : '—' })]),
                    ]);
                    row.addEventListener('click', rowClick(table.name));
                    return row;
                }));

                // relationships
                const rows = [];
                shown().forEach((child) => child.foreign_keys.forEach((key) => {
                    const parent = byName.get(key.table);
                    const text = `${child.name}.${key.columns.join(', ')} ${key.table}.${key.foreign_columns.join(', ')} ${key.on_delete} ${key.on_update}`.toLowerCase();
                    if (!state.search.trim() || text.includes(state.search.trim().toLowerCase())) {
                        const unique = child.columns.some((column) => column.name === key.columns[0] && column.unique);
                        rows.push(html('tr', { class: 'border-b border-pedago-line transition-colors duration-150 hover:bg-pedago-row' }, [
                            html('td', { class: 'num px-3 py-2 text-[12.5px]' }, [tableLink(child.name), html('span', { class: 'text-pedago-muted', text: '.' + key.columns.join(', ') })]),
                            html('td', { class: 'num px-3 py-2 text-[12.5px]' }, [tableLink(parent ? parent.name : key.table), html('span', { class: 'text-pedago-muted', text: '.' + key.foreign_columns.join(', ') })]),
                            html('td', { class: 'px-3 py-2 text-xs', text: unique ? 'one to one' : 'many to one' }),
                            html('td', { class: 'num px-3 py-2 text-xs', text: key.on_delete }),
                            html('td', { class: 'num px-3 py-2 text-xs', text: key.on_update }),
                        ]));
                    }
                }));
                el('relations-body').replaceChildren(...(rows.length ? rows : [html('tr', {}, [html('td', { class: 'px-3 py-6 text-center text-sm text-pedago-muted', colspan: '5', text: 'No relationship matches.' })])]));
            };

            document.querySelectorAll('#tables-table th[data-sort]').forEach((th) => th.addEventListener('click', () => {
                const key = th.dataset.sort;
                state.sort = { key, dir: state.sort.key === key ? -state.sort.dir : 1 };
                renderLists();
            }));
            el('relations-body').addEventListener('click', (event) => { const link = event.target.closest('[data-goto]'); if (link) { select(link.dataset.goto); } });

            // ---------------- modules, tabs, search ----------------
            const renderModules = () => {
                const holder = el('db-modules');
                holder.replaceChildren(...data.modules.map((module) => {
                    const on = state.modules.has(module);
                    const count = tables.filter((table) => table.module === module).length;
                    const chip = html('button', { class: `inline-flex min-h-8 items-center gap-1.5 rounded-full border px-2.5 text-xs font-semibold transition-colors duration-150 ${on ? 'border-pedago-blue bg-pedago-tint text-pedago-blue-dark' : 'border-pedago-line text-pedago-muted hover:bg-pedago-canvas'}`, type: 'button', 'aria-pressed': String(on) }, [html('i', { class: 'inline-block size-2 rounded-full', style: `background:${COLORS[module] || '#14203f'}` }), `${module} `, html('span', { class: 'num font-medium', text: String(count) })]);
                    chip.addEventListener('click', () => { state.modules.has(module) ? state.modules.delete(module) : state.modules.add(module); renderModules(); redraw(); renderLists(); });
                    return chip;
                }));
                el('er-legend').replaceChildren(...data.modules.filter((module) => state.modules.has(module)).map((module) => html('li', { class: 'inline-flex items-center gap-1.5' }, [html('i', { class: 'inline-block size-2.5 rounded-sm', style: `background:${COLORS[module] || '#14203f'}` }), module])).concat([html('li', { class: 'num', text: 'PK primary key · FK foreign key · UQ unique · N many · 1 one' })]));
            };
            const setTab = (name) => {
                state.tab = name;
                document.querySelectorAll('#db-tabs [data-tab]').forEach((button) => button.setAttribute('aria-selected', String(button.dataset.tab === name)));
                ['diagram', 'tables', 'mapping', 'relations'].forEach((key) => { el('panel-' + key).hidden = key !== name; });
                el('db-actions').querySelector('#db-export-svg').classList.toggle('hidden', name !== 'diagram');
                if (name === 'diagram') requestAnimationFrame(fit);
            };
            document.querySelectorAll('#db-tabs [data-tab]').forEach((button) => button.addEventListener('click', () => setTab(button.dataset.tab)));
            let timer;
            el('db-search').addEventListener('input', (event) => { clearTimeout(timer); timer = setTimeout(() => { state.search = event.target.value; redraw(); renderLists(); }, 120); });

            // ---------------- export ----------------
            const download = (name, type, content) => {
                const link = document.createElement('a');
                link.href = URL.createObjectURL(new Blob([content], { type }));
                link.download = name;
                document.body.append(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(link.href), 1000);
            };
            const copyText = async (text, message) => {
                try { await navigator.clipboard.writeText(text); } catch (error) { download('schema.mmd', 'text/plain', text); message = 'Clipboard blocked, downloaded instead'; }
                const note = el('db-count'); const previous = note.textContent; note.textContent = message; setTimeout(() => { note.textContent = previous; }, 2200);
            };
            const mermaid = (list) => {
                const clean = (type) => type.replace(/[^a-zA-Z0-9]+/g, '_').replace(/_$/, '');
                const names = new Set(list.map((table) => table.name));
                let text = 'erDiagram\n';
                list.forEach((table) => {
                    text += `  ${table.name} {\n`;
                    table.columns.forEach((column) => { text += `    ${clean(column.type) || 'text'} ${column.name}${column.pk ? ' PK' : column.fk ? ' FK' : column.unique ? ' UK' : ''}\n`; });
                    text += '  }\n';
                });
                list.forEach((child) => child.foreign_keys.forEach((key) => { if (names.has(key.table)) text += `  ${key.table} ||--o{ ${child.name} : "${key.columns.join(',')}"\n`; }));
                return text;
            };
            el('db-export-mermaid').addEventListener('click', () => copyText(mermaid(shown()), 'Mermaid diagram copied'));
            el('db-export-json').addEventListener('click', () => download('pedago-schema.json', 'application/json', JSON.stringify(data, null, 2)));
            el('db-export-svg').addEventListener('click', () => {
                const list = shown();
                if (!list.length) return;
                let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
                list.forEach((table) => { const p = pos.get(table.name); minX = Math.min(minX, p.x); minY = Math.min(minY, p.y); maxX = Math.max(maxX, p.x + NODE_W); maxY = Math.max(maxY, p.y + nodeHeight(table)); });
                const copy = svg.cloneNode(true);
                copy.removeAttribute('class');
                copy.setAttribute('viewBox', `${minX - 20} ${minY - 20} ${maxX - minX + 40} ${maxY - minY + 40}`);
                copy.setAttribute('width', String(Math.round(maxX - minX + 40)));
                copy.setAttribute('height', String(Math.round(maxY - minY + 40)));
                copy.querySelector('#er-viewport').removeAttribute('transform');
                copy.insertBefore(svgEl('rect', { x: minX - 20, y: minY - 20, width: maxX - minX + 40, height: maxY - minY + 40, fill: '#f8f9fd' }), copy.firstChild);
                copy.querySelectorAll('[tabindex],[role],[style],[aria-label]').forEach((node) => { ['tabindex', 'role', 'style', 'aria-label'].forEach((attr) => node.removeAttribute(attr)); });
                download('pedago-er-diagram.svg', 'image/svg+xml', '<' + '?xml version="1.0" encoding="UTF-8"?>\n' + new XMLSerializer().serializeToString(copy));
            });

            // ---------------- go ----------------
            autoLayout();
            renderModules();
            renderLists();
            redraw();
            requestAnimationFrame(fit);
            window.addEventListener('resize', () => { if (state.tab === 'diagram') fit(); });
        })();
    </script>
@endpush

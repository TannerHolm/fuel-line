<script setup lang="ts">
import FFLayout from '@/layouts/FFLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Option {
    value: string;
    label: string;
}

const props = defineProps<{
    options: {
        retailer_types: Option[];
        lead_sources: Option[];
        owners: { id: number; name: string }[];
    };
}>();

const page = usePage();
const summary = computed(
    () =>
        (page.props as any).flash?.import_summary as
            | { created: number; skipped: { line: number; name: string; reason: string }[]; failed: { line: number; name: string; reason: string }[] }
            | undefined,
);

const currentUserId = (page.props.auth as any)?.user?.id as number;
const ownerId = ref<number | ''>(props.options.owners.some((o) => o.id === currentUserId) ? currentUserId : '');
const defaultSource = ref<string>('cold_call');

const raw = ref('');
const processing = ref(false);

// Column aliases → canonical field. Headers are matched with case and
// punctuation stripped, so "Business Name", "business_name" and "NAME" all land.
const FIELDS: Record<string, string[]> = {
    name: ['name', 'business', 'businessname', 'account', 'accountname', 'location', 'locationname', 'store', 'storename', 'company', 'companyname'],
    city: ['city', 'town'],
    state: ['state', 'st', 'province'],
    retailer_type: ['type', 'retailertype', 'category'],
    decision_maker: ['contact', 'contactname', 'decisionmaker', 'owner', 'manager'],
    phone: ['phone', 'phonenumber', 'tel', 'telephone', 'mobile', 'cell'],
    email: ['email', 'emailaddress'],
    lead_source: ['source', 'leadsource'],
    notes: ['notes', 'note', 'comments', 'comment'],
};

const normalize = (s: string) => s.toLowerCase().replace(/[^a-z0-9]/g, '');

const fieldFor = (header: string): string | null => {
    const n = normalize(header);
    for (const [field, aliases] of Object.entries(FIELDS)) {
        if (aliases.includes(n)) return field;
    }
    return null;
};

/** Minimal delimited-text parser that survives quoted fields and CRLF. */
const parseDelimited = (text: string, delim: string): string[][] => {
    const rows: string[][] = [];
    let row: string[] = [];
    let cell = '';
    let inQuotes = false;

    for (let i = 0; i < text.length; i++) {
        const ch = text[i];
        if (inQuotes) {
            if (ch === '"' && text[i + 1] === '"') {
                cell += '"';
                i++;
            } else if (ch === '"') {
                inQuotes = false;
            } else {
                cell += ch;
            }
        } else if (ch === '"') {
            inQuotes = true;
        } else if (ch === delim) {
            row.push(cell);
            cell = '';
        } else if (ch === '\n' || ch === '\r') {
            if (ch === '\r' && text[i + 1] === '\n') i++;
            row.push(cell);
            cell = '';
            rows.push(row);
            row = [];
        } else {
            cell += ch;
        }
    }
    if (cell !== '' || row.length > 0) {
        row.push(cell);
        rows.push(row);
    }
    return rows.filter((r) => r.some((c) => c.trim() !== ''));
};

const parsed = computed(() => {
    const text = raw.value.trim();
    if (text === '') return null;

    const delim = text.split('\n')[0].includes('\t') ? '\t' : ',';
    const grid = parseDelimited(text, delim);
    if (grid.length === 0) return null;

    const headerFields = grid[0].map(fieldFor);
    if (!headerFields.includes('name')) {
        return { error: 'The first row must be headers, and one column must be the business name. Copy the template above.', rows: [] as Record<string, string>[] };
    }
    if (grid.length === 1) {
        return { error: 'Headers found, but no data rows under them.', rows: [] as Record<string, string>[] };
    }

    const rows = grid.slice(1).map((cells) => {
        const row: Record<string, string> = {};
        headerFields.forEach((field, i) => {
            if (field && (cells[i] ?? '').trim() !== '') row[field] = cells[i].trim();
        });
        return row;
    });

    return { error: null, rows };
});

const problems = (row: Record<string, string>) => (row.name ? null : 'Missing a business name');
const readyCount = computed(() => (parsed.value?.rows ?? []).filter((r) => !problems(r)).length);

const onFile = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    file.text().then((t) => (raw.value = t));
    (e.target as HTMLInputElement).value = '';
};

const template = 'Name,City,State,Type,Contact,Phone,Email,Source,Notes';
const copiedTemplate = ref(false);
const copyTemplate = async () => {
    await navigator.clipboard.writeText(template);
    copiedTemplate.value = true;
    setTimeout(() => (copiedTemplate.value = false), 2000);
};

const submit = () => {
    if (!parsed.value || parsed.value.error || processing.value) return;
    processing.value = true;
    router.post(
        '/accounts/import',
        {
            rows: parsed.value.rows,
            owner_id: ownerId.value || null,
            lead_source: defaultSource.value || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => (raw.value = ''),
            onFinish: () => (processing.value = false),
        },
    );
};
</script>

<template>
    <Head title="Import locations" />
    <FFLayout>
        <div class="mx-auto max-w-5xl">
            <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="ff-display text-4xl">Import locations</h1>
                    <p class="mt-1.5 text-white/[0.55]">Paste a prospect list from a spreadsheet. Every row enters the pipeline as a Qualified Prospect.</p>
                </div>
                <Link href="/accounts" class="ff-btn ff-btn-ghost no-underline">Back to accounts</Link>
            </div>

            <div v-if="summary" class="ff-card mb-6 border-l-2 p-5" :class="summary.created > 0 ? 'border-l-ff-success' : 'border-l-ff-warning'">
                <div class="ff-label-sm" :class="summary.created > 0 ? 'text-ff-success' : 'text-ff-warning'">
                    {{ summary.created }} imported · {{ summary.skipped.length }} skipped · {{ summary.failed.length }} failed
                </div>
                <div v-if="summary.skipped.length" class="mt-3">
                    <div class="ff-field-label text-white/[0.55]">Skipped</div>
                    <p v-for="s in summary.skipped" :key="'s' + s.line" class="mt-1 text-[13px] text-white/[0.72]">
                        Row {{ s.line }} — {{ s.name }}: {{ s.reason }}
                    </p>
                </div>
                <div v-if="summary.failed.length" class="mt-3">
                    <div class="ff-field-label text-white/[0.55]">Failed — fix these rows and paste them again</div>
                    <p v-for="f in summary.failed" :key="'f' + f.line" class="mt-1 text-[13px] text-white/[0.72]">
                        Row {{ f.line }} — {{ f.name }}: {{ f.reason }}
                    </p>
                </div>
                <Link v-if="summary.created > 0" href="/accounts" class="ff-btn ff-btn-secondary mt-4 no-underline">View accounts</Link>
            </div>

            <div class="ff-card p-6">
                <div class="ff-label-sm text-ff-tan-light">Paste rows</div>
                <p class="mt-2 text-[13px] text-white/[0.55]">
                    First row must be headers. Recognized columns: Name, City, State, Type, Contact, Phone, Email, Source, Notes —
                    only Name is required. Copying cells straight out of Excel or Sheets works.
                </p>

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <code class="ff-mono border border-white/[0.18] bg-ink px-3 py-2 text-xs text-white/[0.72]">{{ template }}</code>
                    <button type="button" class="ff-btn ff-btn-ghost" @click="copyTemplate">{{ copiedTemplate ? 'Copied' : 'Copy header row' }}</button>
                    <label class="ff-btn ff-btn-ghost cursor-pointer">
                        Upload CSV
                        <input type="file" accept=".csv,.tsv,.txt,text/csv" class="hidden" @change="onFile" />
                    </label>
                </div>

                <textarea
                    v-model="raw"
                    rows="8"
                    class="ff-input mt-4 w-full font-mono text-xs leading-relaxed"
                    placeholder="Name,City,State,Type,Contact,Phone,Email,Source,Notes
Big Sky Sinclair,Belgrade,MT,Convenience,Dana Ross,406-555-0141,dana@bigsky.example,Cold call,Near the truck route"
                ></textarea>

                <div class="mt-4 flex flex-col gap-4 sm:flex-row">
                    <div class="flex flex-1 flex-col gap-2">
                        <label class="ff-field-label" for="owner">Assign to</label>
                        <select id="owner" v-model="ownerId" class="ff-input ff-input-sm">
                            <option value="">Unassigned</option>
                            <option v-for="o in options.owners" :key="o.id" :value="o.id">{{ o.name }}</option>
                        </select>
                    </div>
                    <div class="flex flex-1 flex-col gap-2">
                        <label class="ff-field-label" for="default_source">Lead source when a row has none</label>
                        <select id="default_source" v-model="defaultSource" class="ff-input ff-input-sm">
                            <option value="">Leave blank</option>
                            <option v-for="s in options.lead_sources" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div v-if="parsed" class="mt-6">
                <div v-if="parsed.error" class="ff-card border-l-2 border-l-ff-warning p-5">
                    <span class="ff-label-sm text-ff-warning">{{ parsed.error }}</span>
                </div>

                <template v-else>
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-4">
                        <span class="ff-label text-white/[0.55]">{{ readyCount }} of {{ parsed.rows.length }} rows ready</span>
                        <button type="button" class="ff-btn ff-btn-primary" :disabled="readyCount === 0 || processing" @click="submit">
                            Import {{ readyCount }} {{ readyCount === 1 ? 'location' : 'locations' }}
                        </button>
                    </div>

                    <div class="ff-card overflow-x-auto">
                        <table class="w-full border-collapse text-sm sm:min-w-[720px]">
                            <thead>
                                <tr class="border-b border-white/[0.18]">
                                    <th class="ff-label-sm px-5 py-2.5 text-left text-white/[0.55]">Name</th>
                                    <th class="ff-label-sm px-5 py-2.5 text-left text-white/[0.55]">Location</th>
                                    <th class="ff-label-sm hidden px-5 py-2.5 text-left text-white/[0.55] md:table-cell">Type</th>
                                    <th class="ff-label-sm hidden px-5 py-2.5 text-left text-white/[0.55] md:table-cell">Contact</th>
                                    <th class="ff-label-sm hidden px-5 py-2.5 text-left text-white/[0.55] lg:table-cell">Phone</th>
                                    <th class="ff-label-sm hidden px-5 py-2.5 text-left text-white/[0.55] lg:table-cell">Source</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in parsed.rows" :key="i" class="border-b border-white/[0.1] last:border-b-0">
                                    <td class="px-5 py-3 font-medium" :class="problems(row) ? 'text-ff-warning' : 'text-white'">
                                        {{ row.name || '—' }}
                                        <span v-if="problems(row)" class="ff-label-sm mt-1 block text-ff-warning">{{ problems(row) }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-white/[0.72]">{{ [row.city, row.state].filter(Boolean).join(', ') || '—' }}</td>
                                    <td class="hidden px-5 py-3 text-white/[0.72] md:table-cell">{{ row.retailer_type ?? '—' }}</td>
                                    <td class="hidden px-5 py-3 text-white/[0.72] md:table-cell">{{ row.decision_maker ?? '—' }}</td>
                                    <td class="hidden px-5 py-3 text-white/[0.72] lg:table-cell">{{ row.phone ?? '—' }}</td>
                                    <td class="hidden px-5 py-3 text-white/[0.72] lg:table-cell">{{ row.lead_source ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>
    </FFLayout>
</template>

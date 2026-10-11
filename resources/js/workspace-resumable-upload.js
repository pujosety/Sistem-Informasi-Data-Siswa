/** Native Blade component. Import once from the existing Vite app entry. */
const hex = (buffer) => Array.from(new Uint8Array(buffer), (n) => n.toString(16).padStart(2, '0')).join('');
const checksum = async (blob) => hex(await crypto.subtle.digest('SHA-256', await blob.arrayBuffer()));

export function mountWorkspaceResumableUploads(root = document) {
    root.querySelectorAll('[data-workspace-resumable-upload]').forEach((el) => {
        if (el.dataset.mounted) return;
        el.dataset.mounted = 'true';
        const fileInput = el.querySelector('[data-upload-file]');
        const start = el.querySelector('[data-upload-start]');
        const pause = el.querySelector('[data-upload-pause]');
        const cancel = el.querySelector('[data-upload-cancel]');
        const progress = el.querySelector('[data-upload-progress]');
        const status = el.querySelector('[data-upload-status]');
        const token = el.querySelector('input[name="_token"]').value;
        const endpoint = el.dataset.endpoint.replace(/\/$/, '');
        const key = `workspace-upload:${el.dataset.scope}`;
        let session = null;
        let busy = false;
        let paused = false;
        let cancelling = false;
        let completionConfirmed = false;
        let xhr = null;
        let request = null;
        // Storage is advisory only; backend authorization is authoritative.
        try { session = JSON.parse(localStorage.getItem(key) || 'null'); } catch { /* storage disabled */ }
        const remember = () => {
            try {
                if (session) localStorage.setItem(key, JSON.stringify({ id: session.id }));
                else localStorage.removeItem(key);
            } catch { /* still works within this page */ }
        };
        const say = (text) => { status.textContent = text; };
        const buttons = () => {
            start.disabled = busy || cancelling;
            pause.disabled = !busy || paused;
            cancel.disabled = cancelling || (!session && !busy);
            fileInput.disabled = busy || cancelling;
        };
        const paint = (bytes, size) => { progress.value = size ? Math.min(100, (bytes / size) * 100) : 0; };
        const decode = async (response) => {
            let body;
            try { body = await response.json(); } catch { throw new Error(`Server merespons HTTP ${response.status}; sesi tetap dapat dilanjutkan.`); }
            if (!response.ok) {
                const message = Object.values(body.errors || {}).flat().join(' ') || body.message || `HTTP ${response.status}`;
                const error = new Error(message);
                error.status = response.status;
                throw error;
            }
            return body;
        };
        const api = async (url, method = 'GET', body) => {
            request = new AbortController();
            const controller = request;
            try {
                return await decode(await fetch(url, {
                    method, credentials: 'same-origin', signal: controller.signal,
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token, ...(body ? { 'Content-Type': 'application/json' } : {}) },
                    ...(body ? { body: JSON.stringify(body) } : {}),
                }));
            } finally { if (request === controller) request = null; }
        };
        const sendChunk = (blob, index, hash) => new Promise((resolve, reject) => {
            const form = new FormData();
            form.append('index', String(index));
            form.append('checksum', hash);
            form.append('chunk', blob, 'chunk.bin');
            const upload = new XMLHttpRequest();
            xhr = upload;
            upload.open('POST', `${endpoint}/${session.id}/chunks`);
            upload.setRequestHeader('Accept', 'application/json');
            upload.setRequestHeader('X-CSRF-TOKEN', token);
            upload.upload.onprogress = (event) => {
                if (event.lengthComputable) paint(session.received_bytes + Math.floor(blob.size * event.loaded / event.total), session.size);
            };
            upload.onload = () => {
                xhr = null;
                let body;
                try { body = JSON.parse(upload.responseText); } catch { reject(new Error(`HTTP ${upload.status}`)); return; }
                if (upload.status >= 200 && upload.status < 300) resolve(body);
                else reject(new Error(Object.values(body.errors || {}).flat().join(' ') || body.message || `HTTP ${upload.status}`));
            };
            upload.onerror = () => { xhr = null; reject(new Error('Jaringan terputus. Klik Lanjutkan untuk memeriksa progres server.')); };
            upload.onabort = () => { xhr = null; reject(new DOMException('Paused', 'AbortError')); };
            upload.send(form);
        });
        const completed = () => {
            completionConfirmed = true;
            const id = session.file_id;
            paint(session.size, session.size);
            session = null;
            remember();
            say('Berkas tersimpan. Segarkan daftar Drive untuk melihatnya.');
            el.dispatchEvent(new CustomEvent('workspace-upload-completed', { bubbles: true, detail: { fileId: id } }));
        };
        const cancelSession = async () => {
            if (!session) {
                say(completionConfirmed ? 'Berkas sudah tersimpan; pembatalan tidak menghapusnya.' : 'Unggahan dibatalkan sebelum sesi dibuat.');
                return;
            }
            let result;
            try { result = await api(`${endpoint}/${session.id}`, 'DELETE'); }
            catch (error) {
                if (error.status !== 404) throw error;
                session = null; remember(); paint(0, 1);
                say('Sesi tidak tersedia lagi. Tidak ada berkas tersimpan yang dihapus.');
                return;
            }
            if (result.state === 'completed') { session = result; completed(); }
            else { session = null; remember(); paint(0, 1); say('Unggahan dibatalkan dan potongan sementara dibersihkan.'); }
        };
        if (session) say('Sesi tersimpan ditemukan. Pilih kembali berkas yang sama lalu klik Lanjutkan, atau Batalkan.');
        buttons();
        start.addEventListener('click', async () => {
            if (busy || cancelling) return;
            busy = true; paused = false; completionConfirmed = false; buttons();
            let reconciling = false;
            try {
                // A finalize response may have been lost; status is checked
                // before requiring a file so a completed retry is recoverable.
                if (session) {
                    reconciling = true;
                    session = await api(`${endpoint}/${session.id}`);
                    reconciling = false;
                    if (session.state === 'completed') { completed(); return; }
                    if (session.state !== 'open') throw new Error('Sesi tidak aktif. Batalkan sesi ini lalu mulai unggahan baru.');
                    paint(session.received_bytes, session.size);
                }
                const file = fileInput.files[0];
                if (!file) throw new Error('Pilih berkas yang akan diunggah atau dilanjutkan.');
                if (file.size < 1 || file.size > Number(el.dataset.maxBytes)) throw new Error('Ukuran berkas melewati batas unggahan.');
                if (!globalThis.crypto?.subtle) throw new Error('Checksum memerlukan HTTPS atau localhost dan browser modern.');
                say('Memeriksa SHA-256 berkas…');
                const hash = await checksum(file);
                if (paused || cancelling) return;
                if (session) {
                    if (session.size !== file.size || session.checksum !== hash) throw new Error('Berkas tidak sama dengan sesi tersimpan.');
                } else {
                    // Do not abort creation: its UUID must reach the browser so
                    // cancellation can target a created session, not leak one.
                    session = await api(endpoint, 'POST', { name: file.name, size: file.size, checksum: hash, folder: el.dataset.folder || null });
                    remember();
                }
                while (session.received_bytes < session.size && !paused && !cancelling) {
                    const index = session.next_index;
                    const blob = file.slice(index * session.chunk_bytes, Math.min(file.size, (index + 1) * session.chunk_bytes));
                    const chunkHash = await checksum(blob);
                    if (paused || cancelling) break;
                    say(`Mengunggah potongan ${index + 1}…`);
                    // On lost acknowledgement resume GET reconciles next_index;
                    // server also accepts a byte-identical checksum retry.
                    session = await sendChunk(blob, index, chunkHash);
                    remember(); paint(session.received_bytes, session.size);
                }
                if (!paused && !cancelling && session.received_bytes === session.size) {
                    say('Memverifikasi dan menyimpan berkas…');
                    session = await api(`${endpoint}/${session.id}/finalize`, 'POST');
                    completed();
                } else if (!cancelling) say('Dijeda. Klik Lanjutkan untuk meneruskan dari progres server.');
            } catch (error) {
                if (error.status === 404 && reconciling) {
                    session = null; remember();
                    say('Sesi tidak tersedia lagi. Periksa daftar Drive sebelum memulai unggahan baru.');
                    return;
                }
                say(error.name === 'AbortError' ? 'Dijeda. Klik Lanjutkan untuk memeriksa progres server.' : error.message);
            } finally {
                busy = false;
                if (cancelling) {
                    try { await cancelSession(); } catch (error) { say(`Pembatalan belum terkonfirmasi: ${error.message}. Coba Batalkan lagi.`); }
                    cancelling = false;
                }
                buttons();
            }
        });
        pause.addEventListener('click', () => {
            paused = true;
            xhr?.abort();
            // Do not abort create/finalize: a lost response can hide completion.
            buttons();
            say('Menjeda setelah operasi yang sedang berjalan selesai…');
        });
        cancel.addEventListener('click', async () => {
            if (cancelling) return;
            cancelling = true; paused = true; xhr?.abort(); buttons();
            if (busy) return; // worker finally owns DELETE after in-flight POST
            try { await cancelSession(); } catch (error) { say(`Pembatalan belum terkonfirmasi: ${error.message}. Coba lagi.`); }
            finally { cancelling = false; buttons(); }
        });
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => mountWorkspaceResumableUploads());
else mountWorkspaceResumableUploads();

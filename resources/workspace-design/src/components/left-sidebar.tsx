import { useState } from 'preact/hooks';
import { useEditor } from '../context';
import { api, config } from '../api';
export function LeftSidebar() {
  const { addText, addShape, addImage, setBackground } = useEditor();
  const [file, setFile] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const button = 'block w-full p-2 mb-2 rounded border border-zinc-200 hover:bg-zinc-100 text-left';
  return <aside class="w-52 bg-white border-r p-3 overflow-auto shrink-0">
    <h2 class="font-semibold mb-3">Tools</h2>
    {(['heading','subheading','body'] as const).map(p => <button class={button} onClick={() => addText(p)}>Add {p}</button>)}
    {(['rect','circle','triangle','line'] as const).map(p => <button class={button} onClick={() => addShape(p)}>Add {p}</button>)}
    <label>Background <input type="color" onInput={e => setBackground('color', e.currentTarget.value)} /></label>
    <h3 class="font-semibold mt-5 mb-2">Private Drive image</h3>
    <p class="text-xs mb-2">Paste your Drive file UUID. Only scanned PNG, JPEG or WebP versions can be used. New uploads and custom fonts are disabled.</p>
    <label class="text-xs">File UUID<input class="w-full border p-2 my-2" value={file} onInput={e => setFile(e.currentTarget.value)} /></label>
    <button class={button} disabled={busy} onClick={async () => {
      setError(''); setBusy(true);
      try { const asset = await api<{url:string}>('POST', config.assets, {file}); await addImage(asset.url); }
      catch(e) { setError(e instanceof Error ? e.message : 'Asset unavailable'); }
      finally { setBusy(false); }
    }}>Add private image</button>
    {error && <p role="alert" class="text-red-600 text-xs">{error}</p>}
  </aside>;
}

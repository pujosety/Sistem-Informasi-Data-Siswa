import { useEffect, useRef, useState } from 'preact/hooks';
import { EditorContext } from './context';
import { useCanvasState } from './hooks/use-canvas';
import { useProject } from './hooks/use-project';
import { Editor } from './components/editor';
import { loadFonts } from './fonts';
import { config, privateSource } from './api';
export function App() {
  const scheduler = useRef<() => void>(() => {});
  const canvas = useCanvasState(() => scheduler.current());
  const design = useProject(canvas);
  scheduler.current = design.scheduleSave;
  const [fontsReady,setFontsReady] = useState(false);
  const [fontError,setFontError] = useState('');
  const [frameReady,setFrameReady] = useState(false);
  useEffect(() => { loadFonts().then(() => setFontsReady(true)).catch(() => setFontError('Bundled fonts could not load. Reload to retry.')); },[]);
  useEffect(() => {
    if (!design.project) return;
    canvas.setCanvasSize(design.project.document.width,design.project.document.height,{reflow:false});
    canvas.setActiveCanvas(design.project.document.pages[0].id);
    setFrameReady(true);
  },[design.loadedDesignId]);
  if (fontError) return <main role="alert">{fontError}</main>;
  if (design.loading || !fontsReady) return <main>Loading private design…</main>;
  if (!design.project) return <main role="alert">{design.error || 'Project unavailable'} <a href={config.index}>Back to designs</a></main>;
  if (!frameReady) return <main>Loading canvas…</main>;
  const value = {
    ...canvas,...design,customFonts:[], templates:[],
    setCanvasSize: (width:number,height:number,options?:{reflow?:boolean}) => {
      if (canvas.isCanvasBusy() || design.pages.some(page => !canvas.canvasMap.current.has(page.id))) { window.dispatchEvent(new CustomEvent("workspace-design-load-error", {detail:"Wait for all pages to load before resizing."})); return; }
      if (width * height * design.pages.length > 20000000) { window.dispatchEvent(new CustomEvent("workspace-design-load-error", {detail:"Resize exceeds 20 megapixels across all pages."})); return; }
      canvas.setCanvasSize(width,height,options);
    },
    importFont:async () => { throw new Error('Font upload disabled until guarded storage adapter exists'); },
    addImage: (url:string) => canvas.addImage(privateSource(url)),
  };
  return <EditorContext.Provider value={value}>
    <div class="flex flex-col h-full">
      {design.error && <div role="alert" class="p-3 bg-red-50 text-red-800 shrink-0">
        {design.error} {design.conflict && <button class="underline ml-3" onClick={() => { if (confirm('Reload and discard local edits? Export them first.')) location.reload(); }}>Reload server revision</button>}
      </div>}
      <div class="px-3 py-1 bg-zinc-100 text-xs shrink-0">OpenDesign · private school project · revision {design.project.revision} <button class="underline ml-3" onClick={design.exportLocal}>Download local JSON backup</button></div>
      <div class="flex-1 min-h-0"><Editor /></div>
    </div>
  </EditorContext.Provider>;
}

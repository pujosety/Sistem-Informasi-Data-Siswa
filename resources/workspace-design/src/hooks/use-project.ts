import { useState, useRef, useEffect } from 'preact/hooks';
import type { Page, Design } from '../types';
import { api, ApiError, config, normalizeCanvas } from '../api';
type Document = { width: number; height: number; pages: {id:string; title:string; canvas:unknown}[] };
type Project = { id: string; name: string; revision: number; document: Document };
export function useProject(canvas: ReturnType<typeof import('./use-canvas').useCanvasState>) {
  const [project, setProject] = useState<Project | null>(null);
  const projectRef = useRef<Project | null>(null);
  const [pages, setPages] = useState<Page[]>([]);
  const pagesRef = useRef<Page[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [saveStatus, setSaveStatus] = useState<'saved'|'pending'|'saving'|'error'>('saved');
  const [error, setError] = useState('');
  const [conflict, setConflict] = useState(false);
  const conflictRef = useRef(false);
  const dirty = useRef(false);
  const changes = useRef(0);
  const [autoSave, setAutoSaveState] = useState(true);
  const autoRef = useRef(true);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const queue = useRef<Promise<void>>(Promise.resolve());
  const saveRef = useRef<() => Promise<void>>(async () => {});
  const scheduleSave = () => {
    if (!projectRef.current) return;
    dirty.current = true; changes.current++; setSaveStatus('pending');
    if (timer.current) clearTimeout(timer.current);
    if (autoRef.current && !conflictRef.current) timer.current = setTimeout(() => { timer.current = null; void saveRef.current(); }, 800);
  };
  const updatePages = (next: Page[]) => { pagesRef.current = next; setPages(next); scheduleSave(); };
  useEffect(() => {
    let cancelled = false;
    api<Project>('GET', config.endpoint).then(p => {
      if (cancelled) return;
      projectRef.current = p; setProject(p);
      const next = p.document.pages.map((page,i) => ({id:page.id, design_id:p.id, title:page.title, canvas_json:JSON.stringify(page.canvas), sort_order:i, created_at:''}));
      pagesRef.current = next; setPages(next);
    }).catch(e => setError(e instanceof Error ? e.message : 'Load failed')).finally(() => !cancelled && setLoading(false));
    const loadError = (event: Event) => { setError((event as CustomEvent<string>).detail); setSaveStatus('error'); };
    window.addEventListener('workspace-design-load-error', loadError);
    const beforeUnload = (event: BeforeUnloadEvent) => { if (dirty.current) { event.preventDefault(); event.returnValue = ''; } };
    window.addEventListener('beforeunload', beforeUnload);
    return () => { cancelled = true; if (timer.current) clearTimeout(timer.current); window.removeEventListener('beforeunload', beforeUnload); window.removeEventListener('workspace-design-load-error', loadError); };
  }, []);
  const snapshot = (): Document => {
    if (canvas.isCanvasBusy()) throw new Error("Canvas history is restoring. Wait before saving or exporting.");
    const {width,height} = canvas.getCanvasSize();
    return {width,height,pages:pagesRef.current.map(page => {
      const live = canvas.canvasMap.current.get(page.id);
      if (!live) throw new Error('A page is still loading. Wait before saving or exporting JSON.');
      return {id:page.id,title:page.title,canvas:normalizeCanvas(live.toJSON())};
    })};
  };
  const saveNow = async () => {
    if (!projectRef.current) return;
    if (conflictRef.current) { setError('Revision conflict: export your local JSON or PNG/PDF, then reload. No overwrite was sent.'); return; }
    if (timer.current) clearTimeout(timer.current);
    timer.current = null;
    const p = projectRef.current;
    const change = changes.current;
    setSaving(true); setSaveStatus('saving');
    try {
      const document = snapshot();
      const saved = await api<Project>('PUT', config.endpoint, {revision:p.revision,name:p.name,document});
      // Never replace local page/canvas/name state with an older in-flight snapshot.
      projectRef.current = {...projectRef.current!,revision:saved.revision};
      setProject(projectRef.current); setError('');
      dirty.current = changes.current !== change;
      setSaveStatus(dirty.current ? 'pending' : 'saved');
    } catch(e) {
      setSaveStatus('error'); setError(e instanceof Error ? e.message : 'Save failed');
      if (e instanceof ApiError && e.status === 409) { conflictRef.current = true; setConflict(true); autoRef.current = false; setAutoSaveState(false); }
    } finally { setSaving(false); }
  };
  const saveDesign = () => { const next = queue.current.then(saveNow); queue.current = next.catch(() => {}); return next; };
  saveRef.current = saveDesign;
  const setAutoSave = (enabled: boolean) => {
    if (conflictRef.current) return;
    autoRef.current = enabled; setAutoSaveState(enabled);
    if (!enabled && timer.current) { clearTimeout(timer.current); timer.current = null; }
    if (enabled && dirty.current) scheduleSave();
  };
  const renameDesign = async (id:string,name:string) => {
    if (!projectRef.current || projectRef.current.id !== id) return;
    name = name.trim().slice(0,180); if (!name) return;
    projectRef.current = {...projectRef.current,name}; setProject(projectRef.current); scheduleSave();
  };
  const addPage = async (afterPageId?: string) => {
    const size = canvas.getCanvasSize();
    if (pagesRef.current.length >= 10 || size.width * size.height * (pagesRef.current.length + 1) > 20000000) { setError('Page limit: 10 pages and 20 megapixels total.'); return; }
    const next = [...pagesRef.current];
    const id = crypto.randomUUID();
    const index = afterPageId ? next.findIndex(p => p.id === afterPageId) + 1 : next.length;
    next.splice(index,0,{id,design_id:config.project,title:'Page '+(next.length+1),canvas_json:JSON.stringify({objects:[],background:'#ffffff'}),sort_order:index,created_at:''});
    updatePages(next); canvas.setActiveCanvas(id);
  };
  const duplicatePage = async (pageId:string) => {
    const size = canvas.getCanvasSize();
    if (pagesRef.current.length >= 10 || size.width * size.height * (pagesRef.current.length + 1) > 20000000) { setError('Page limit: 10 pages and 20 megapixels total.'); return; }
    const old = pagesRef.current.find(p => p.id === pageId);
    if (!old || !canvas.canvasMap.current.has(pageId)) return;
    const copy = {...old,id:crypto.randomUUID(),title:old.title.slice(0,90)+' copy',canvas_json:canvas.getCanvasJSONForPage(pageId)};
    const next = [...pagesRef.current]; next.splice(next.findIndex(p => p.id === pageId)+1,0,copy);
    updatePages(next); canvas.setActiveCanvas(copy.id);
  };
  const deletePage = async (pageId:string) => {
    if (pagesRef.current.length < 2) return;
    const next = pagesRef.current.filter(p => p.id !== pageId);
    updatePages(next); if (canvas.activeCanvasId === pageId) canvas.setActiveCanvas(next[0].id);
  };
  const renamePage = async (id:string,title:string) => {
    title = title.trim().slice(0,100); if (!title) return;
    updatePages(pagesRef.current.map(p => p.id === id ? {...p,title} : p));
  };
  const navigate = (_path:string) => {
    if (dirty.current && !confirm('Leave with unsaved changes? Export local JSON first if needed.')) return;
    location.assign(config.index);
  };
  const exportLocal = () => {
    try {
      const blob = new Blob([JSON.stringify({name:projectRef.current?.name,revision:projectRef.current?.revision,document:snapshot()},null,2)],{type:'application/json'});
      const url = URL.createObjectURL(blob); const link = document.createElement('a'); link.href = url; link.download = 'design-local-recovery.json'; link.click(); setTimeout(() => URL.revokeObjectURL(url),1000);
    } catch(e) { setError(e instanceof Error ? e.message : 'Snapshot failed'); }
  };
  const activeDesign: Design | null = project ? {id:project.id,name:project.name,width:project.document.width,height:project.document.height,canvas_json:'{}',thumbnail_url:null,created_at:'',updated_at:''} : null;
  return {project,activeDesign,pages,activePageId:canvas.activeCanvasId,activePage:pages.find(p => p.id === canvas.activeCanvasId) ?? null,
    loading,saving,saveStatus,autoSave,setAutoSave,renameDesign,saveDesign,scheduleSave,addPage,duplicatePage,deletePage,renamePage,navigate,error,conflict,exportLocal,
    loadedDesignId:project?.id ?? null,switchToPage:canvas.setActiveCanvas};
}

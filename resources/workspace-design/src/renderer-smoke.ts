import { Canvas, Rect, Textbox } from 'fabric';
import { buildCarouselPDF, renderPageToPNG } from './lib/export';
import { loadFonts } from './fonts';

// Real renderer fixture, deliberately independent of Laravel/auth/API.
const output = document.getElementById('result')!;
async function smoke() {
  await loadFonts();
  const canvases = ['first', 'second'].map(id => new Canvas(id, {width:320, height:240, enableRetinaScaling:false}));
  for (const [i, canvas] of canvases.entries()) {
    const dpr = window.devicePixelRatio || 1;
    canvas.setDimensions({width:320*dpr,height:240*dpr});
    canvas.setViewportTransform([dpr,0,0,dpr,0,0]);
    canvas.backgroundColor = '#ffffff';
    canvas.add(new Rect({left:20,top:20,width:100,height:80,fill:i ? '#ff0000' : '#0000ff'}));
    canvas.add(new Textbox('Private school design', {left:20,top:130,width:260,fontFamily:'Inter',fontSize:20}));
    canvas.renderAll();
  }
  // Exercise upstream JSON hydration, not just newly constructed objects.
  const json = canvases[0].toJSON();
  await canvases[0].loadFromJSON(json);
  canvases[0].renderAll();
  const png = renderPageToPNG(canvases[0]);
  output.textContent = 'PNG rendered; decoding…';
  const image = new Image();
  await new Promise<void>((resolve, reject) => {
    const timeout = setTimeout(() => reject(new Error('PNG decode timed out')), 10000);
    image.onload = () => { clearTimeout(timeout); resolve(); };
    image.onerror = () => { clearTimeout(timeout); reject(new Error('PNG decode failed')); };
    image.src = png;
  });
  if (image.width !== 640 || image.height !== 480) throw new Error(`Wrong PNG size: ${image.width}x${image.height}`);
  const pdf = await buildCarouselPDF(canvases,320,240);
  const bytes = new Uint8Array(pdf.output('arraybuffer'));
  const signature = new TextDecoder().decode(bytes.slice(0,5));
  if (signature !== '%PDF-' || pdf.getNumberOfPages() !== 2) throw new Error('PDF signature/pages invalid');
  const result = {status:'passed',pngWidth:image.width,pngHeight:image.height,pngBytes:atob(png.split(',')[1]).length,pdfSignature:signature,pdfPages:pdf.getNumberOfPages(),pdfBytes:bytes.length,hydratedObjects:canvases[0].getObjects().length,devicePixelRatio:window.devicePixelRatio};
  output.textContent = JSON.stringify(result,null,2);
  output.dataset.status = 'passed';
  await Promise.all(canvases.map(c => c.dispose()));
}
smoke().catch(error => { output.textContent = String(error); output.dataset.status = 'failed'; console.error(error); });

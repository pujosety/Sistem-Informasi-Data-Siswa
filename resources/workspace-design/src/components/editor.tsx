import { useState } from "preact/hooks";
import { CanvasArea } from "./canvas-area";
import { Toolbar } from "./toolbar";
import { LeftSidebar } from "./left-sidebar";
import { RightSidebar } from "./right-sidebar";
import { PagesBar } from "./pages-bar";

export function Editor() {
  const [panel, setPanel] = useState<"tools"|"properties"|null>(null);
  return (
    <div class="flex flex-col h-full w-full">
      <Toolbar />
      <nav class="lg:hidden flex gap-2 p-2 border-b" aria-label="Editor panels">
        <button aria-expanded={panel === "tools"} aria-controls="design-tools" onClick={() => setPanel(panel === "tools" ? null : "tools")}>Tools</button>
        <button aria-expanded={panel === "properties"} aria-controls="design-properties" onClick={() => setPanel(panel === "properties" ? null : "properties")}>Properties</button>
      </nav>
      <div class="flex flex-1 min-h-0">
        <div id="design-tools" class={`${panel === "tools" ? "flex" : "hidden"} lg:flex shrink-0 overflow-auto`}><LeftSidebar /></div>
        <div class="flex-1 flex flex-col min-w-0">
          <CanvasArea />
          <PagesBar />
        </div>
        <div id="design-properties" class={`${panel === "properties" ? "flex" : "hidden"} lg:flex shrink-0 overflow-auto`}><RightSidebar /></div>
      </div>
    </div>
  );
}

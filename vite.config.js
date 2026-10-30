import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
  plugins: [react(), tailwindcss()],
  define: {
    "process.env.NODE_ENV": JSON.stringify("production"),
  },
  build: {
    outDir: "build",
    emptyOutDir: true,
    cssCodeSplit: false,
    lib: {
      entry: "src/main.jsx",
      name: "BryviumChat",
      formats: ["iife"],
      fileName: () => "bryvium-chat.js",
      cssFileName: "bryvium-chat",
    },
  },
});

import { createRoot } from "react-dom/client";
import "./index.css";

function App() {
  return (
    <div className="bwfc:fixed bwfc:bottom-4 bwfc:right-4 bwfc:rounded-lg bwfc:bg-blue-600 bwfc:px-4 bwfc:py-2 bwfc:text-white">
      Bryvium build OK
    </div>
  );
}

const mount = document.getElementById("bwfc-root");
if (mount) {
  createRoot(mount).render(<App />);
}

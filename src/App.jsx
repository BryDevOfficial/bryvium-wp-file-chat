const config = window.BryviumChatConfig || {};

export default function App() {
  const position =
    config.position === "bottom-left" ? "bwfc:left-4" : "bwfc:right-4";
  const primaryColor = /^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i.test(
    config.primaryColor || "",
  )
    ? config.primaryColor
    : "#2563eb";

  return (
    <aside
      className={`bwfc:fixed bwfc:bottom-4 ${position} bwfc:z-[9999]`}
      aria-label={config.widgetTitle || "Bryvium chat"}
      style={{ "--bwfc-primary-color": primaryColor }}
    >
      <div className="bwfc:rounded-lg bwfc:bg-[var(--bwfc-primary-color)] bwfc:px-4 bwfc:py-3 bwfc:text-sm bwfc:font-medium bwfc:text-white bwfc:shadow-lg">
        <p className="bwfc:m-0 bwfc:font-semibold">
          {config.widgetTitle || "Chat with us"}
        </p>
        <p className="bwfc:m-0 bwfc:mt-1">
          {config.greeting || "Hello! How can we help you today?"}
        </p>
        <span className="bwfc:sr-only">
          {config.placeholder || "Type your question"}
        </span>
      </div>
    </aside>
  );
}

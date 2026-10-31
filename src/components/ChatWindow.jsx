import MessageInput from "./MessageInput.jsx";
import MessageList from "./MessageList.jsx";

export default function ChatWindow({
  isOpen,
  onClose,
  onSend,
  messages,
  isLoading,
  title,
  placeholder,
  position,
}) {
  const side = position === "bwfc:left-4" ? "bwfc:left-0" : "bwfc:right-0";

  return (
    <section
      id="bwfc-chat-window"
      className={`bwfc:pointer-events-auto bwfc:absolute ${side} bwfc:bottom-18 bwfc:flex bwfc:h-[min(70vh,38rem)] bwfc:max-h-[calc(100dvh-6rem)] bwfc:w-[min(24rem,calc(100vw-2rem))] bwfc:flex-col bwfc:overflow-hidden bwfc:rounded-2xl bwfc:border bwfc:border-slate-200 bwfc:bg-white bwfc:text-slate-900 bwfc:shadow-2xl bwfc:transition-all bwfc:duration-200 bwfc:dark:border-slate-700 bwfc:dark:bg-slate-900 bwfc:dark:text-slate-100 ${
        isOpen
          ? "bwfc:visible bwfc:translate-y-0 bwfc:scale-100 bwfc:opacity-100"
          : "bwfc:invisible bwfc:translate-y-2 bwfc:scale-95 bwfc:opacity-0"
      }`}
      role="dialog"
      aria-modal="false"
      aria-labelledby="bwfc-chat-title"
      aria-hidden={!isOpen}
      inert={!isOpen}
    >
      <header className="bwfc:flex bwfc:shrink-0 bwfc:items-center bwfc:justify-between bwfc:gap-3 bwfc:bg-[var(--bwfc-primary-color)] bwfc:px-5 bwfc:py-4 bwfc:text-white">
        <h2
          id="bwfc-chat-title"
          className="bwfc:m-0 bwfc:truncate bwfc:text-base bwfc:font-semibold"
        >
          {title}
        </h2>
        <button
          type="button"
          className="bwfc:flex bwfc:h-9 bwfc:w-9 bwfc:shrink-0 bwfc:items-center bwfc:justify-center bwfc:rounded-full bwfc:border-0 bwfc:bg-transparent bwfc:text-white bwfc:hover:bg-white/15 bwfc:focus-visible:outline-2 bwfc:focus-visible:outline-offset-2 bwfc:focus-visible:outline-white"
          aria-label="Close chat"
          onClick={onClose}
        >
          <svg
            aria-hidden="true"
            className="bwfc:h-5 bwfc:w-5"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
          >
            <path d="m6 6 12 12M18 6 6 18" />
          </svg>
        </button>
      </header>
      <MessageList
        messages={messages}
        isLoading={isLoading}
        onSuggestionClick={onSend}
      />
      <MessageInput
        onSend={onSend}
        placeholder={placeholder}
        isLoading={isLoading}
      />
    </section>
  );
}

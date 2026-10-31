import { useEffect, useRef } from "react";

function renderMessageText(text) {
  const parts = [];
  const urlPattern = /https?:\/\/[^\s<>"']+/gi;
  let match;
  let lastIndex = 0;

  while ((match = urlPattern.exec(text)) !== null) {
    let url = match[0];
    while (/[.,!?;:)\]}]$/.test(url)) {
      url = url.slice(0, -1);
    }

    if (!url) {
      continue;
    }

    const urlEnd = match.index + url.length;
    if (match.index > lastIndex) {
      parts.push(text.slice(lastIndex, match.index));
    }

    try {
      const parsedUrl = new URL(url);
      if (parsedUrl.protocol === "http:" || parsedUrl.protocol === "https:") {
        parts.push({ href: parsedUrl.href, text: url });
      } else {
        parts.push(url);
      }
    } catch {
      parts.push(url);
    }

    lastIndex = urlEnd;
  }

  if (lastIndex < text.length) {
    parts.push(text.slice(lastIndex));
  }

  return parts.map((part, index) =>
    typeof part === "string" ? (
      <span key={`text-${index}`}>{part}</span>
    ) : (
      <a
        key={`link-${index}`}
        href={part.href}
        target="_blank"
        rel="noopener noreferrer"
        className="bwfc:break-all bwfc:underline"
      >
        {part.text}
      </a>
    ),
  );
}

export default function MessageList({
  messages,
  isLoading,
  onSuggestionClick,
}) {
  const endRef = useRef(null);

  useEffect(() => {
    const reduceMotion = window.matchMedia(
      "(prefers-reduced-motion: reduce)",
    ).matches;
    endRef.current?.scrollIntoView({
      behavior: reduceMotion ? "auto" : "smooth",
      block: "end",
    });
  }, [messages]);

  return (
    <div
      className="bwfc:flex bwfc:min-h-0 bwfc:flex-1 bwfc:flex-col bwfc:gap-3 bwfc:overflow-y-auto bwfc:bg-slate-50 bwfc:p-4 bwfc:dark:bg-slate-950"
      role="log"
      aria-label="Chat messages"
      aria-live="polite"
      aria-relevant="additions text"
    >
      {messages.map((message) => {
        const isVisitor = message.sender === "visitor";

        return (
          <div
            key={message.id}
            className={`bwfc:flex ${
              isVisitor ? "bwfc:justify-end" : "bwfc:justify-start"
            }`}
          >
            <div className="bwfc:flex bwfc:max-w-[90%] bwfc:flex-col bwfc:items-start bwfc:gap-2">
              <p
                className={`bwfc:m-0 bwfc:max-w-full bwfc:whitespace-pre-wrap bwfc:break-words bwfc:rounded-2xl bwfc:px-4 bwfc:py-2.5 bwfc:text-sm bwfc:leading-relaxed ${
                  isVisitor
                    ? "bwfc:self-end bwfc:rounded-br-md bwfc:bg-[var(--bwfc-primary-color)] bwfc:text-[var(--bwfc-primary-text-color)]"
                    : "bwfc:rounded-bl-md bwfc:bg-white bwfc:text-slate-800 bwfc:shadow-sm bwfc:ring-1 bwfc:ring-slate-200 bwfc:dark:bg-slate-800 bwfc:dark:text-slate-100 bwfc:dark:ring-slate-700"
                }`}
              >
                {renderMessageText(message.text)}
              </p>
              {Array.isArray(message.suggestions) &&
                message.suggestions.length > 0 && (
                  <div className="bwfc:flex bwfc:flex-wrap bwfc:gap-2">
                    {message.suggestions.map((suggestion) => (
                      <button
                        key={suggestion.id || suggestion.title}
                        type="button"
                        className="bwfc:min-h-11 bwfc:rounded-full bwfc:border bwfc:border-slate-300 bwfc:bg-white bwfc:px-4 bwfc:py-2 bwfc:text-left bwfc:text-xs bwfc:text-slate-700 bwfc:hover:bg-slate-100 bwfc:dark:border-slate-600 bwfc:dark:bg-slate-800 bwfc:dark:text-slate-100 bwfc:dark:hover:bg-slate-700"
                        disabled={isLoading}
                        onClick={() => onSuggestionClick(suggestion.title)}
                      >
                        {suggestion.title}
                      </button>
                    ))}
                  </div>
                )}
            </div>
          </div>
        );
      })}
      {isLoading && (
        <p
          className="bwfc:m-0 bwfc:w-fit bwfc:animate-pulse bwfc:rounded-2xl bwfc:rounded-bl-md bwfc:bg-white bwfc:px-4 bwfc:py-2.5 bwfc:text-sm bwfc:text-slate-600 bwfc:shadow-sm bwfc:ring-1 bwfc:ring-slate-200 bwfc:dark:bg-slate-800 bwfc:dark:text-slate-300 bwfc:dark:ring-slate-700"
          role="status"
        >
          Assistant is typing...
        </p>
      )}
      <div ref={endRef} />
    </div>
  );
}

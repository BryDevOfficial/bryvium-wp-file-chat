import { useEffect, useRef } from "react";

export default function MessageList({ messages }) {
  const endRef = useRef(null);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: "smooth", block: "end" });
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
            <p
              className={`bwfc:m-0 bwfc:max-w-[85%] bwfc:whitespace-pre-wrap bwfc:break-words bwfc:rounded-2xl bwfc:px-4 bwfc:py-2.5 bwfc:text-sm bwfc:leading-relaxed ${
                isVisitor
                  ? "bwfc:rounded-br-md bwfc:bg-[var(--bwfc-primary-color)] bwfc:text-white"
                  : "bwfc:rounded-bl-md bwfc:bg-white bwfc:text-slate-800 bwfc:shadow-sm bwfc:ring-1 bwfc:ring-slate-200 bwfc:dark:bg-slate-800 bwfc:dark:text-slate-100 bwfc:dark:ring-slate-700"
              }`}
            >
              {message.text}
            </p>
          </div>
        );
      })}
      <div ref={endRef} />
    </div>
  );
}

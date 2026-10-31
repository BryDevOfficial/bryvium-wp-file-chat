import { useState } from "react";

export default function MessageInput({ onSend, placeholder, isLoading }) {
  const [value, setValue] = useState("");

  function submitMessage(event) {
    event.preventDefault();
    const message = value.trim();
    if (!message || isLoading || Array.from(message).length > 300) {
      return;
    }

    if (onSend(message)) {
      setValue("");
    }
  }

  return (
    <form
      className="bwfc:flex bwfc:shrink-0 bwfc:items-end bwfc:gap-2 bwfc:border-t bwfc:border-slate-200 bwfc:bg-white bwfc:p-3 bwfc:dark:border-slate-700 bwfc:dark:bg-slate-900"
      onSubmit={submitMessage}
    >
      <label className="bwfc:sr-only" htmlFor="bwfc-chat-message">
        Message
      </label>
      <textarea
        id="bwfc-chat-message"
        className="bwfc:max-h-28 bwfc:min-h-11 bwfc:min-w-0 bwfc:flex-1 bwfc:resize-y bwfc:rounded-xl bwfc:border bwfc:border-slate-300 bwfc:bg-white bwfc:px-3 bwfc:py-2.5 bwfc:text-sm bwfc:text-slate-900 bwfc:placeholder:text-slate-500 bwfc:focus:border-[var(--bwfc-primary-color)] bwfc:focus:outline-none bwfc:focus:ring-2 bwfc:focus:ring-[var(--bwfc-primary-color)]/25 bwfc:dark:border-slate-600 bwfc:dark:bg-slate-800 bwfc:dark:text-slate-100"
        rows="1"
        disabled={isLoading}
        placeholder={placeholder}
        value={value}
        onChange={(event) =>
          setValue(Array.from(event.target.value).slice(0, 300).join(""))
        }
      />
      <button
        type="submit"
        className="bwfc:flex bwfc:h-11 bwfc:w-11 bwfc:shrink-0 bwfc:items-center bwfc:justify-center bwfc:rounded-xl bwfc:border-0 bwfc:bg-[var(--bwfc-primary-color)] bwfc:text-white bwfc:hover:brightness-110 bwfc:focus-visible:outline-2 bwfc:focus-visible:outline-offset-2 bwfc:focus-visible:outline-[var(--bwfc-primary-color)] bwfc:disabled:cursor-not-allowed bwfc:disabled:opacity-50"
        aria-label="Send message"
        disabled={isLoading || !value.trim()}
      >
        <svg
          aria-hidden="true"
          className="bwfc:h-5 bwfc:w-5"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
        >
          <path d="m22 2-7 20-4-9-9-4Z" />
          <path d="M22 2 11 13" />
        </svg>
      </button>
    </form>
  );
}

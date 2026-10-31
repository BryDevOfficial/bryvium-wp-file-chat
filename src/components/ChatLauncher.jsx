export default function ChatLauncher({ buttonRef, isOpen, onClick, title }) {
  const label = isOpen ? "Close chat" : `Open chat with ${title}`;

  return (
    <button
      ref={buttonRef}
      type="button"
      className="bwfc:pointer-events-auto bwfc:ml-auto bwfc:flex bwfc:h-14 bwfc:w-14 bwfc:items-center bwfc:justify-center bwfc:rounded-full bwfc:border-0 bwfc:bg-[var(--bwfc-primary-color)] bwfc:text-white bwfc:shadow-lg bwfc:transition bwfc:duration-200 bwfc:hover:brightness-110 bwfc:focus-visible:outline-2 bwfc:focus-visible:outline-offset-2 bwfc:focus-visible:outline-[var(--bwfc-primary-color)]"
      aria-label={label}
      aria-expanded={isOpen}
      aria-controls="bwfc-chat-window"
      onClick={onClick}
    >
      {isOpen ? (
        <svg
          aria-hidden="true"
          className="bwfc:h-6 bwfc:w-6"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
        >
          <path d="m6 6 12 12M18 6 6 18" />
        </svg>
      ) : (
        <svg
          aria-hidden="true"
          className="bwfc:h-6 bwfc:w-6"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
        >
          <path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z" />
        </svg>
      )}
    </button>
  );
}

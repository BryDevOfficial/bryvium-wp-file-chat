import { useEffect, useRef, useState } from "react";
import ChatLauncher from "./components/ChatLauncher.jsx";
import ChatWindow from "./components/ChatWindow.jsx";

const config = window.BryviumChatConfig || {};
const position =
  config.position === "bottom-left" ? "bwfc:left-4" : "bwfc:right-4";
const primaryColor = /^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i.test(
  config.primaryColor || "",
)
  ? config.primaryColor
  : "#2563eb";

export default function App() {
  const [isOpen, setIsOpen] = useState(false);
  const launcherRef = useRef(null);
  const [messages, setMessages] = useState(() => [
    {
      id: "greeting",
      sender: "bot",
      text: config.greeting || "Hello! How can we help you today?",
    },
  ]);

  function closeChat() {
    setIsOpen(false);
    launcherRef.current?.focus();
  }

  useEffect(() => {
    if (!isOpen) {
      return undefined;
    }

    function closeOnEscape(event) {
      if (event.key === "Escape") {
        closeChat();
      }
    }

    document.addEventListener("keydown", closeOnEscape);
    return () => document.removeEventListener("keydown", closeOnEscape);
  }, [isOpen]);

  function handleSend(text) {
    const message = text.trim();
    if (!message) {
      return;
    }

    setMessages((currentMessages) => {
      const id = currentMessages.length;
      return [
        ...currentMessages,
        {
          id: `visitor-${id}`,
          sender: "visitor",
          text: message,
        },
        {
          id: `bot-${id + 1}`,
          sender: "bot",
          text: "Thanks for your message. Our chat assistant will respond here soon.",
        },
      ];
    });
  }

  return (
    <aside
      className={`bwfc:fixed bwfc:bottom-4 ${position} bwfc:z-[999] bwfc:pointer-events-none`}
      aria-label={config.widgetTitle || "Bryvium chat"}
      style={{ "--bwfc-primary-color": primaryColor }}
    >
      <ChatWindow
        isOpen={isOpen}
        onClose={closeChat}
        onSend={handleSend}
        messages={messages}
        title={config.widgetTitle || "Chat with us"}
        placeholder={config.placeholder || "Type your question"}
        position={position}
      />
      <ChatLauncher
        buttonRef={launcherRef}
        isOpen={isOpen}
        onClick={() => setIsOpen((open) => !open)}
        title={config.widgetTitle || "Chat with us"}
      />
    </aside>
  );
}

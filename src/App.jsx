import { useEffect, useRef, useState } from "react";
import ChatLauncher from "./components/ChatLauncher.jsx";
import ChatWindow from "./components/ChatWindow.jsx";
import useChat from "./hooks/useChat.js";

const config = window.BryviumChatConfig || {};
const position =
  config.position === "bottom-left" ? "bwfc:left-4" : "bwfc:right-4";
const primaryColor = /^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i.test(
  config.primaryColor || "",
)
  ? config.primaryColor
  : "#2563eb";
const normalizedPrimaryColor =
  primaryColor.length === 4
    ? `#${primaryColor[1]}${primaryColor[1]}${primaryColor[2]}${primaryColor[2]}${primaryColor[3]}${primaryColor[3]}`
    : primaryColor;
const primaryChannels = [1, 3, 5].map((index) =>
  Number.parseInt(normalizedPrimaryColor.slice(index, index + 2), 16) / 255,
);
const primaryLuminance = primaryChannels
  .map((channel) =>
    channel <= 0.04045
      ? channel / 12.92
      : ((channel + 0.055) / 1.055) ** 2.4,
  )
  .reduce(
    (luminance, channel, index) =>
      luminance + channel * [0.2126, 0.7152, 0.0722][index],
    0,
  );
const primaryTextColor =
  (1.05 / (primaryLuminance + 0.05)) >=
  ((primaryLuminance + 0.05) / 0.05)
    ? "#ffffff"
    : "#000000";

export default function App() {
  const [isOpen, setIsOpen] = useState(false);
  const launcherRef = useRef(null);
  const chatWindowRef = useRef(null);
  const wasOpenRef = useRef(false);
  const { messages, isLoading, sendMessage } = useChat({
    greeting: config.greeting || "Hello! How can we help you today?",
    fallbackMessage:
      config.fallbackMessage ||
      "Sorry, I could not find an answer to that question.",
    saveHistory: config.saveHistory === true,
  });

  function closeChat() {
    setIsOpen(false);
  }

  useEffect(() => {
    if (!isOpen) {
      if (wasOpenRef.current) {
        wasOpenRef.current = false;
        launcherRef.current?.focus();
      }
      return undefined;
    }

    wasOpenRef.current = true;
    const focusableSelector =
      'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const focusableElements = () =>
      Array.from(
        chatWindowRef.current?.querySelectorAll(focusableSelector) || [],
      ).filter((element) => element.getAttribute("aria-hidden") !== "true");
    const firstFocusable = () => focusableElements()[0];
    const lastFocusable = () => {
      const elements = focusableElements();
      return elements[elements.length - 1];
    };
    firstFocusable()?.focus();

    function keepFocusInChat(event) {
      if (!chatWindowRef.current?.contains(event.target)) {
        firstFocusable()?.focus();
      }
    }

    function handleChatKeydown(event) {
      if (event.key === "Escape") {
        closeChat();
        return;
      }

      if (event.key !== "Tab") {
        return;
      }

      const first = firstFocusable();
      const last = lastFocusable();
      if (!first || !last) {
        event.preventDefault();
      } else if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }

    document.addEventListener("keydown", handleChatKeydown);
    document.addEventListener("focusin", keepFocusInChat);
    return () => {
      document.removeEventListener("keydown", handleChatKeydown);
      document.removeEventListener("focusin", keepFocusInChat);
    };
  }, [isOpen]);

  return (
    <aside
      className={`bwfc:fixed bwfc:bottom-4 ${position} bwfc:z-[999] bwfc:pointer-events-none`}
      aria-label={config.widgetTitle || "Bryvium chat"}
      style={{
        "--bwfc-primary-color": primaryColor,
        "--bwfc-primary-text-color": primaryTextColor,
      }}
    >
      <ChatWindow
        windowRef={chatWindowRef}
        isOpen={isOpen}
        onClose={closeChat}
        onSend={sendMessage}
        messages={messages}
        isLoading={isLoading}
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

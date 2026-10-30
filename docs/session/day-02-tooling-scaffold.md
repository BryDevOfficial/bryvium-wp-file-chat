# D2: Tooling Scaffold (PHPCS, Git, Editor, Vite + Tailwind)

**Branch:** `feature/d02-tooling`

Commit D1 first if you haven't, then branch:

```powershell
git add -A; git commit -m "feat(build): add plugin bootstrap, constants and lifecycle hooks"
git checkout develop; git merge --no-ff feature/d01-bootstrap
git checkout -b feature/d02-tooling
```

All tooling here is **dev-only**. The shipped plugin still contains just PHP files plus one JS and one CSS file.

**Prerequisites:** Node 20+ and Composer (`node -v`, `composer -V`). If Composer isn't installed, skip the PHPCS install step. The config files are still useful, and you can run PHPCS later.

## 1. `.gitignore`

```gitignore
# Dependencies
/node_modules/
/vendor/

# Runtime data: never commit real user content
knowledge.json
/bryvium-chat/

# Tooling caches and local config
.phpcs-cache
phpcs.xml
composer.lock.bak
*.log

# OS / editor
.DS_Store
Thumbs.db
.idea/
.vscode/*
!.vscode/extensions.json

# Release artifacts
/dist/
*.zip
```

`build/` is intentionally **not** ignored, because site owners install the compiled output.

## 2. `.editorconfig`

```ini
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true
indent_style = tab

[*.{js,jsx,json,css,yml,yaml,md}]
indent_style = space
indent_size = 2

[*.md]
trim_trailing_whitespace = false
```

PHP uses tabs (WPCS requires them). Frontend files use 2 spaces (Vite/React convention).

## 3. `composer.json` (dev-only)

```json
{
  "name": "bryvium/wp-file-chat",
  "description": "Local, file-based chat widget for WordPress.",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": ">=8.5"
  },
  "require-dev": {
    "wp-coding-standards/wpcs": "^3.0",
    "phpcompatibility/phpcompatibility-wp": "^2.1",
    "dealerdirect/phpcodesniffer-composer-installer": "^1.0"
  },
  "config": {
    "allow-plugins": {
      "dealerdirect/phpcodesniffer-composer-installer": true
    }
  },
  "scripts": {
    "lint": "phpcs",
    "fix": "phpcbf"
  }
}
```

## 4. `phpcs.xml.dist`

```xml
<?xml version="1.0"?>
<ruleset name="Bryvium WP File Chat">
	<description>WordPress Coding Standards for Bryvium WP File Chat.</description>

	<file>.</file>
	<exclude-pattern>/node_modules/*</exclude-pattern>
	<exclude-pattern>/vendor/*</exclude-pattern>
	<exclude-pattern>/build/*</exclude-pattern>
	<exclude-pattern>/src/*</exclude-pattern>
	<exclude-pattern>/dist/*</exclude-pattern>

	<arg name="extensions" value="php"/>
	<arg name="colors"/>
	<arg value="ps"/>
	<arg name="cache" value=".phpcs-cache"/>

	<rule ref="WordPress"/>
	<rule ref="PHPCompatibilityWP"/>

	<config name="testVersion" value="8.5-"/>
	<config name="minimum_supported_wp_version" value="7.1.4"/>

	<rule ref="WordPress.WP.I18n">
		<properties>
			<property name="text_domain" type="array">
				<element value="bryvium-wp-file-chat"/>
			</property>
		</properties>
	</rule>

	<rule ref="WordPress.NamingConventions.PrefixAllGlobals">
		<properties>
			<property name="prefixes" type="array">
				<element value="bryvium"/>
			</property>
		</properties>
	</rule>
</ruleset>
```

## 5. `package.json`

```json
{
  "name": "bryvium-wp-file-chat",
  "private": true,
  "version": "0.1.0",
  "type": "module",
  "scripts": {
    "dev": "vite build --watch",
    "build": "vite build"
  }
}
```

There's no dev server because WordPress serves the page. `npm run dev` rebuilds on save, and you refresh the browser.

Install everything as **devDependencies**. React is bundled into the output, so it is never a runtime dependency:

```powershell
npm install -D vite @vitejs/plugin-react @tailwindcss/vite tailwindcss react react-dom
composer install
```

## 6. `vite.config.js`

```js
import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
  plugins: [react(), tailwindcss()],
  define: {
    // Lib mode does not replace this automatically; React needs it.
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
      // Stable filenames: PHP will cache-bust with filemtime().
      fileName: () => "bryvium-chat.js",
      cssFileName: "bryvium-chat",
    },
  },
});
```

The output is one self-contained IIFE script, with no hashed names and no code splitting. `emptyOutDir` will delete `build/.gitkeep`, which is fine because real files will be there.

## 7. `src/index.css`

```css
/*
 * Tailwind v4 WITHOUT preflight, so we never reset the host theme's styles.
 * All utilities are prefixed: write classes as `bwfc:flex`, `bwfc:p-4`.
 */
@layer theme, base, components, utilities;
@import "tailwindcss/theme.css" layer(theme) prefix(bwfc);
@import "tailwindcss/utilities.css" layer(utilities) prefix(bwfc);

/* Minimal scoped reset, applied only inside our widget root. */
#bwfc-root,
#bwfc-root *,
#bwfc-root *::before,
#bwfc-root *::after {
  box-sizing: border-box;
}
```

## 8. `src/main.jsx` (placeholder to prove the pipeline)

```jsx
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
```

## 9. Manual test (3 steps)

1. `npm run build` should produce `build/bryvium-chat.js` and `build/bryvium-chat.css`, with no errors. Open the CSS and confirm it contains `.bwfc\:fixed` and does **not** contain a global `h1`/`button`/`body` reset.
2. `composer lint` should report no errors for the D1 files. If it lists `index.php` comment or file-name warnings, paste them to me and I'll adjust the ruleset.
3. Run `composer fix` (it should change nothing), then `git status` should not list `node_modules/` or `vendor/`.

## 10. Troubleshooting

- **Emoji folder path:** if `vite build` or `phpcs` errors with odd path or encoding messages, rename the parent folder (`public-💻...`) to plain ASCII. That's the first thing to try.
- **Class names don't generate:** Tailwind v4 requires the `bwfc:` prefix **in your JSX too**, so plain `flex` won't work. That's the intended collision protection.
- **PowerShell note:** use `;` instead of `&&` if you're on Windows PowerShell 5.1.

## 11. Commit message

```
build(tooling): add PHPCS, EditorConfig and Vite + Tailwind scaffold

Dev-only tooling: WPCS/PHPCompatibility ruleset, prefixed Tailwind v4
without preflight, Vite IIFE build to build/bryvium-chat.{js,css}.
```

## 12. Handoff note for D3

Build outputs are `build/bryvium-chat.js` and `build/bryvium-chat.css`, and the React mount point is `#bwfc-root`. `composer lint` is the quality gate for every PHP day. D3 adds `includes/class-bryvium-storage.php` and wires it via `bryvium_wfc_loaded` and activation.

---

Say **"Start D3"** when ready. That's the storage class: protected directory, atomic JSON read/write with locking, and schema validation.

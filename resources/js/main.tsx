import { StrictMode } from "react"
import { createRoot } from "react-dom/client"
import { createInertiaApp } from "@inertiajs/react"

import "./index.css"
import { ThemeProvider } from "@/components/theme-provider.tsx"
import { Toaster } from "@/components/ui/sonner.tsx"
import { DashboardLayout } from "@/layouts/dashboard-layout"

// Pages that render full-screen without the sidebar/header chrome - every
// other page gets DashboardLayout applied automatically below (mirrors the
// old App.tsx's route nesting, where only these two sat outside it).
const BARE_PAGES = new Set(["login-page", "kiosk-page"])

if ("serviceWorker" in navigator) {
  navigator.serviceWorker.register("/sw.js")
}

// Last-resort backstop against stale auth pages via back/forward. The
// server already sends Cache-Control: no-store (see
// PreventBackHistoryCache) specifically to stop browsers from restoring a
// page straight out of bfcache - a full DOM/JS-heap freeze/thaw that runs
// zero new JS or PHP, so it can show a login form snapshotted from before
// the user logged in (or a dashboard from a since-switched account)
// without ever re-running LoginController's auth checks. If a proxy/CDN
// layer or a particular browser ever fails to honor that header, bfcache
// restoration still always fires `pageshow` with `event.persisted === true`
// - forcing a real reload there guarantees the page is re-fetched fresh
// from the server no matter what happened in between.
window.addEventListener("pageshow", (event) => {
  if (event.persisted) {
    window.location.reload()
  }
})

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob("./pages/**/*.tsx", { eager: true }) as Record<
      string,
      Record<string, React.ComponentType>
    >
    const page = pages[`./pages/${name}.tsx`]
    if (!page) throw new Error(`Page not found: ${name}`)

    // Every page file exports its component as a named export (PascalCase
    // derived from the filename) rather than a default export - grab
    // whichever named export the module has instead of requiring a default.
    const component = Object.values(page)[0]

    if (!BARE_PAGES.has(name)) {
      // @ts-expect-error - Inertia's persistent-layout convention
      component.layout = (page: React.ReactNode) => <DashboardLayout>{page}</DashboardLayout>
    }

    return component
  },
  setup({ el, App, props }) {
    createRoot(el).render(
      <StrictMode>
        <ThemeProvider>
          <App {...props} />
          <Toaster />
        </ThemeProvider>
      </StrictMode>
    )
  },
})

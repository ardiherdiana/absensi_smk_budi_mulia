import { StrictMode } from "react"
import { createRoot } from "react-dom/client"
import { createInertiaApp } from "@inertiajs/react"

import "./index.css"
import { ThemeProvider } from "@/components/theme-provider.tsx"
import { Toaster } from "@/components/ui/sonner.tsx"
import { DashboardLayout } from "@/layouts/dashboard-layout"
import { SppdLayout } from "@/layouts/sppd-layout"

const BARE_PAGES = new Set(["login-page", "kiosk-page", "module-picker-page", "sppd/verifikasi/show"])

if ("serviceWorker" in navigator) {
  navigator.serviceWorker.register("/sw.js")
}

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

    const component = Object.values(page)[0]

    if (!BARE_PAGES.has(name)) {
      const Layout = name.startsWith("sppd/") ? SppdLayout : DashboardLayout
      // @ts-expect-error - Inertia's persistent-layout convention
      component.layout = (page: React.ReactNode) => <Layout>{page}</Layout>
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

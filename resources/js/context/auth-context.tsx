import { usePage } from "@inertiajs/react"
import type { AuthUser } from "@/lib/types"

interface PageProps {
  auth: { user: AuthUser | null }
  [key: string]: unknown
}

// Inertia shares `auth.user` on every page (see HandleInertiaRequests::share)
// - reading it here just wraps usePage() so pages don't need to know that.
// There's no more client-side "loading"/token state: Laravel's own
// auth+role middleware already decided whether this page was reachable
// before Inertia ever rendered it.
export function useAuth() {
  const { props } = usePage<PageProps>()
  return { user: props.auth.user }
}

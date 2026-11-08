declare module '#app' {
  interface PageMeta {
    /** Reachable without login (login, password reset, invitation). */
    public?: boolean
  }
}

export {}

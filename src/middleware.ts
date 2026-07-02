import { NextRequest, NextResponse } from "next/server";

// Lightweight gate: checks for the session cookie only. The real session
// lookup happens server-side in layouts and route handlers.
export function middleware(req: NextRequest) {
  const hasSession = Boolean(req.cookies.get("session")?.value);
  const { pathname } = req.nextUrl;

  if (pathname.startsWith("/dashboard") && !hasSession) {
    const url = req.nextUrl.clone();
    url.pathname = "/login";
    return NextResponse.redirect(url);
  }
  if ((pathname === "/login" || pathname === "/signup") && hasSession) {
    const url = req.nextUrl.clone();
    url.pathname = "/dashboard";
    return NextResponse.redirect(url);
  }
  return NextResponse.next();
}

export const config = {
  matcher: ["/dashboard/:path*", "/login", "/signup"],
};

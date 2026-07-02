import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "FluentAI — AI assistant for your whole team",
  description:
    "A secure, multi-tenant AI workspace for businesses: team chat with GPT models, usage metering, API access, and admin controls.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" className="h-full antialiased">
      <body className="min-h-full flex flex-col bg-zinc-950">{children}</body>
    </html>
  );
}

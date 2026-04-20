"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { ReactNode } from "react";
import { useId, useState } from "react";

const navItems = [
  { href: "/dashboard", label: "Dashboard" },
  { href: "/campaigns", label: "Campaigns" },
  { href: "/domains", label: "Domains" },
  { href: "/traffic-sources", label: "Traffic sources" },
  { href: "/ab-tests", label: "A/B Tests" },
  { href: "/conversions/manual", label: "Manual conversion" },
];

type AdminShellProps = {
  children: ReactNode;
  title: string;
  topbarRight?: ReactNode;
};

export function AdminShell({ children, title, topbarRight }: AdminShellProps) {
  const pathname = usePathname();
  const [mobileOpen, setMobileOpen] = useState(false);
  const sidebarId = useId();

  return (
    <div className="flex min-h-screen bg-slate-100 text-slate-900">
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-slate-900 focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2"
      >
        Skip to main content
      </a>

      <div
        className={
          mobileOpen
            ? "fixed inset-0 z-40 bg-slate-900/50 md:hidden"
            : "hidden"
        }
        aria-hidden={!mobileOpen}
        onClick={() => setMobileOpen(false)}
      />

      <aside
        id={sidebarId}
        className={
          "fixed inset-y-0 left-0 z-50 w-64 shrink-0 border-r border-slate-200 bg-white shadow-sm transition-transform md:static md:translate-x-0 " +
          (mobileOpen ? "translate-x-0" : "-translate-x-full md:translate-x-0")
        }
        aria-label="Application"
      >
        <div className="flex h-14 items-center border-b border-slate-200 px-4">
          <Link
            href="/dashboard"
            className="text-base font-semibold tracking-tight text-slate-900 outline-none ring-slate-900 ring-offset-2 focus-visible:ring-2"
          >
            TDS Admin
          </Link>
        </div>
        <nav className="p-3" aria-label="Main">
          <ul className="space-y-1">
            {navItems.map((item) => {
              const active =
                pathname === item.href || pathname.startsWith(`${item.href}/`);
              return (
                <li key={item.href}>
                  <Link
                    href={item.href}
                    className={
                      "block rounded-md px-3 py-2 text-sm font-medium outline-none transition-colors ring-slate-900 ring-offset-2 focus-visible:ring-2 " +
                      (active
                        ? "bg-slate-900 text-white"
                        : "text-slate-700 hover:bg-slate-100")
                    }
                    aria-current={active ? "page" : undefined}
                    onClick={() => setMobileOpen(false)}
                  >
                    {item.label}
                  </Link>
                </li>
              );
            })}
          </ul>
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col md:pl-0">
        <header className="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-slate-200 bg-white px-4 shadow-sm">
          <button
            type="button"
            className="inline-flex h-10 w-10 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-800 outline-none ring-slate-900 ring-offset-2 hover:bg-slate-50 focus-visible:ring-2 md:hidden"
            aria-controls={sidebarId}
            aria-expanded={mobileOpen}
            onClick={() => setMobileOpen((o) => !o)}
          >
            <span className="sr-only">{mobileOpen ? "Close menu" : "Open menu"}</span>
            <span aria-hidden className="text-lg leading-none">
              {mobileOpen ? "×" : "≡"}
            </span>
          </button>
          <h1 className="min-w-0 flex-1 truncate text-lg font-semibold text-slate-900">
            {title}
          </h1>
          {topbarRight ? (
            <div className="flex shrink-0 items-center gap-2">{topbarRight}</div>
          ) : null}
        </header>

        <main id="main-content" className="flex-1 p-4 md:p-6" tabIndex={-1}>
          {children}
        </main>
      </div>
    </div>
  );
}

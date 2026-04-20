"use client";

import { usePathname } from "next/navigation";
import type { ReactNode } from "react";
import { AdminShell } from "./AdminShell";

const titles: Record<string, string> = {
  "/": "Overview",
  "/reports": "Reports",
  "/settings": "Settings",
};

export function AdminChrome({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const title = titles[pathname] ?? "Dashboard";

  return <AdminShell title={title}>{children}</AdminShell>;
}

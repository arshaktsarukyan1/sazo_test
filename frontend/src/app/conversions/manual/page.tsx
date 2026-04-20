import { Suspense } from "react";
import { ManualConversionForm } from "@/components/conversions/ManualConversionForm";

export default function ManualConversionPage() {
  return (
    <Suspense fallback={<p className="p-6 text-sm text-slate-600">Loading…</p>}>
      <ManualConversionForm />
    </Suspense>
  );
}

import type { Config } from "tailwindcss";

const config: Config = {
  content: ["./src/**/*.{js,ts,jsx,tsx,mdx}"],
  theme: {
    extend: {
      colors: {
        /** Primary surfaces: slate-900 on white meets WCAG AA for UI type */
        ink: {
          DEFAULT: "#0f172a",
          muted: "#334155",
        },
      },
    },
  },
  plugins: [],
};

export default config;

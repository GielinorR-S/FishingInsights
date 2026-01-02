/**
 * Consistent iconography for FishingInsights
 * Plain SVG icons (no external dependencies)
 */

interface IconProps {
  className?: string
  size?: number
}

export function HomeIcon({ className = "w-6 h-6", size }: IconProps) {
  const sizeAttr = size ? { width: size, height: size } : {}
  return (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" {...sizeAttr}>
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
    </svg>
  )
}

export function MapPinIcon({ className = "w-6 h-6", size }: IconProps) {
  const sizeAttr = size ? { width: size, height: size } : {}
  return (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" {...sizeAttr}>
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
  )
}

export function InfoIcon({ className = "w-6 h-6", size }: IconProps) {
  const sizeAttr = size ? { width: size, height: size } : {}
  return (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" {...sizeAttr}>
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
  )
}

export function StarIcon({ className = "w-5 h-5", size, filled = false }: IconProps & { filled?: boolean }) {
  const sizeAttr = size ? { width: size, height: size } : {}
  return (
    <svg className={className} fill={filled ? "currentColor" : "none"} stroke="currentColor" viewBox="0 0 24 24" {...sizeAttr}>
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
    </svg>
  )
}

export function ChevronRightIcon({ className = "w-5 h-5", size }: IconProps) {
  const sizeAttr = size ? { width: size, height: size } : {}
  return (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" {...sizeAttr}>
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
    </svg>
  )
}

export function RefreshIcon({ className = "w-5 h-5", size }: IconProps) {
  const sizeAttr = size ? { width: size, height: size } : {}
  return (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" {...sizeAttr}>
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
    </svg>
  )
}

export function SearchIcon({ className = "w-5 h-5", size }: IconProps) {
  const sizeAttr = size ? { width: size, height: size } : {}
  return (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" {...sizeAttr}>
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
    </svg>
  )
}

export function XIcon({ className = "w-5 h-5", size }: IconProps) {
  const sizeAttr = size ? { width: size, height: size } : {}
  return (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" {...sizeAttr}>
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
    </svg>
  )
}

export function OfflineIcon({ className = "w-5 h-5", size }: IconProps) {
  const sizeAttr = size ? { width: size, height: size } : {}
  return (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" {...sizeAttr}>
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-2.83m-1.414 5.658a9 9 0 01-2.167-9.238m7.824 2.167a1 1 0 111.414 1.414m-1.414-1.414L3 3m8.293 8.293l1.414 1.414" />
    </svg>
  )
}

export function AlertCircleIcon({ className = "w-5 h-5", size }: IconProps) {
  const sizeAttr = size ? { width: size, height: size } : {}
  return (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" {...sizeAttr}>
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
  )
}

export function BarChartIcon({ className = "w-5 h-5", size }: IconProps) {
  const sizeAttr = size ? { width: size, height: size } : {}
  return (
    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" {...sizeAttr}>
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
    </svg>
  )
}


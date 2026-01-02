// Get API base URL and path from environment (defaults to relative /api)
// Using type assertion to avoid TypeScript errors with import.meta.env
const API_BASE_URL = (import.meta as any).env?.VITE_API_BASE_URL || ''
const API_BASE_PATH = (import.meta as any).env?.VITE_API_BASE_PATH || '/api'
const API_BASE = API_BASE_URL + API_BASE_PATH

/**
 * Typed API error object
 */
export interface ApiError {
  message: string
  url: string
  status?: number
  statusText?: string
  isNetworkError: boolean
}

/**
 * Centralized API call function with error handling
 * Uses relative URLs in dev (proxied by Vite) or absolute URLs if API_BASE_URL is set
 */
async function getJson<T>(url: string): Promise<T> {
  // If URL is already absolute (starts with http), use it as-is
  // Otherwise, use relative URL directly (will be proxied by Vite in dev)
  // This ensures /api/locations.php stays as /api/locations.php (not http://localhost:3000/api/locations.php)
  let finalUrl: string = url.startsWith('http') ? url : url
  let status: number | undefined
  let statusText: string | undefined
  let isNetworkError = false

  try {
    const response = await fetch(finalUrl)
    status = response.status
    statusText = response.statusText

    if (!response.ok) {
      const error: ApiError = {
        message: `API error: ${response.status} ${response.statusText}`,
        url: finalUrl,
        status: response.status,
        statusText: response.statusText,
        isNetworkError: false
      }
      throw error
    }

    return await response.json()
  } catch (err) {
    // Check if it's already an ApiError
    if (err && typeof err === 'object' && 'isNetworkError' in err) {
      throw err
    }

    // Check if it's a network error
    if (err instanceof TypeError && (err.message.includes('Failed to fetch') || err.message.includes('NetworkError'))) {
      isNetworkError = true
    }

    const error: ApiError = {
      message: err instanceof Error ? err.message : 'Unknown error occurred',
      url: finalUrl || url,
      status,
      statusText,
      isNetworkError
    }
    throw error
  }
}

export interface Location {
  id: number
  name: string
  region: string | null
  lat: number
  lng: number
  type?: string
  access?: string
}

export interface ForecastResponse {
  error: boolean
  data: {
    location: {
      lat: number
      lng: number
      name: string
      region?: string | null
    }
    timezone: string
    forecast: Array<{
      date: string
      score: number
      weather: {
        temperature_max: number
        temperature_min: number
        wind_speed: number
        wind_direction: number
        precipitation: number
        cloud_cover: number
        conditions: string
      }
      sun: {
        sunrise: string
        sunset: string
        dawn: string
        dusk: string
      }
      tides: {
        events: Array<{
          time: string
          type: 'high' | 'low'
          height: number
        }>
        change_windows: Array<{
          start: string
          end: string
          type: 'rising' | 'falling'
          event_time: string
          event_type: 'high' | 'low'
        }>
      }
      best_bite_windows: Array<{
        start: string
        end: string
        reason: string
        quality: 'excellent' | 'good' | 'fair'
      }>
      recommended_species: Array<{
        id: string
        name: string
        confidence: number
        why: string
      }>
      gear_suggestions: {
        bait: string[]
        lure: string[]
        line_weight: string
        leader: string
        rig: string
      }
      reasons: Array<{
        title: string
        detail: string
        contribution_points: number
        severity: 'positive' | 'negative' | 'neutral'
        category: 'weather' | 'tide' | 'dawn_dusk' | 'seasonality'
      }>
    }>
    cached: boolean
    cached_at: string
    warning?: string
  }
}

/**
 * Get today's date in Australia/Melbourne timezone (YYYY-MM-DD)
 */
function getTodayInMelbourne(): string {
  const now = new Date()
  // Convert to Australia/Melbourne timezone
  const formatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Australia/Melbourne',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  })
  return formatter.format(now)
}

export async function getForecast(lat: number, lng: number, days: number = 7, start?: string, targetSpecies?: string[]): Promise<ForecastResponse> {
  const params = new URLSearchParams({
    lat: lat.toString(),
    lng: lng.toString(),
    days: days.toString()
  })
  // If start not provided, use today in Australia/Melbourne
  if (start) {
    params.append('start', start)
  } else {
    params.append('start', getTodayInMelbourne())
  }
  // Add target species if provided
  if (targetSpecies && targetSpecies.length > 0) {
    params.append('target_species', targetSpecies.join(','))
  }

  return getJson<ForecastResponse>(`${API_BASE}/forecast.php?${params}`)
}

export interface HealthResponse {
  status: string
  php_version: string
  has_pdo: boolean
  has_pdo_sqlite: boolean
  sqlite_db_path: string
  can_write_db: boolean
  can_write_cache: boolean
  timestamp: string
  timezone: string
}

export async function getHealth(): Promise<HealthResponse> {
  return getJson<HealthResponse>(`${API_BASE}/health.php`)
}

export interface LocationsResponse {
  error: boolean
  data: {
    timezone: string
    locations: Array<{
      id: number
      name: string
      region: string
      lat: number
      lng: number
      timezone: string
      type?: string
      access?: string
    }>
  }
}

export async function getLocations(search?: string, region?: string): Promise<LocationsResponse> {
  const params = new URLSearchParams()
  if (search) params.append('search', search)
  if (region) params.append('region', region)
  
  const url = `${API_BASE}/locations.php${params.toString() ? '?' + params.toString() : ''}`
  return getJson<LocationsResponse>(url)
}

// Helper to extract Location[] from LocationsResponse
export function extractLocations(response: LocationsResponse): Location[] {
  return response.data.locations.map(loc => ({
    id: loc.id,
    name: loc.name,
    region: loc.region || null,
    lat: loc.lat,
    lng: loc.lng,
    type: loc.type,
    access: loc.access
  }))
}

export interface Species {
  id: number
  name: string
  common_name: string
  state: string
  region: string | null
  seasonality: string | null
  methods: string | null
  notes: string | null
}

export interface SpeciesResponse {
  error: boolean
  data: {
    timezone: string
    species: Species[]
  }
}

export async function getSpecies(state?: string, region?: string, search?: string): Promise<SpeciesResponse> {
  const params = new URLSearchParams()
  if (state) params.append('state', state)
  if (region) params.append('region', region)
  if (search) params.append('q', search)
  
  const url = `${API_BASE}/species.php${params.toString() ? '?' + params.toString() : ''}`
  return getJson<SpeciesResponse>(url)
}

export interface TodaysBestLocation {
  id: number
  name: string
  region: string
  lat: number
  lng: number
  score: number
  why: string
}

export interface TodaysBestResponse {
  error: boolean
  data: {
    date: string
    timezone: string
    locations: TodaysBestLocation[]
    cached: boolean
    cached_at?: string
  }
}

export async function getTodaysBest(state: string = 'VIC', region?: string, limit: number = 5, speciesId?: string): Promise<TodaysBestResponse> {
  const params = new URLSearchParams({
    state,
    limit: limit.toString()
  })
  if (region) params.append('region', region)
  if (speciesId) params.append('species_id', speciesId)
  
  const url = `${API_BASE}/todays_best.php?${params.toString()}`
  return getJson<TodaysBestResponse>(url)
}


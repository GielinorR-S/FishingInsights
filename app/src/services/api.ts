const API_BASE = '/api'

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

export async function getForecast(lat: number, lng: number, days: number = 7, start?: string): Promise<ForecastResponse> {
  const params = new URLSearchParams({
    lat: lat.toString(),
    lng: lng.toString(),
    days: days.toString()
  })
  if (start) {
    params.append('start', start)
  }

  const response = await fetch(`${API_BASE}/forecast.php?${params}`)
  if (!response.ok) {
    throw new Error(`API error: ${response.status}`)
  }
  return response.json()
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
  const response = await fetch(`${API_BASE}/health.php`)
  if (!response.ok) {
    throw new Error(`Health check failed: ${response.status}`)
  }
  return response.json()
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
    }>
  }
}

export async function getLocations(search?: string, region?: string): Promise<LocationsResponse> {
  const params = new URLSearchParams()
  if (search) params.append('search', search)
  if (region) params.append('region', region)
  
  const url = `${API_BASE}/locations.php${params.toString() ? '?' + params.toString() : ''}`
  const response = await fetch(url)
  if (!response.ok) {
    throw new Error(`Locations API error: ${response.status}`)
  }
  return response.json()
}


import { useEffect, useState } from 'react'
import { useSearchParams, Link } from 'react-router-dom'
import { getForecast, type ForecastResponse } from '../services/api'

function formatTime(isoString: string): string {
  try {
    const date = new Date(isoString)
    return date.toLocaleTimeString('en-AU', { 
      hour: '2-digit', 
      minute: '2-digit',
      hour12: true 
    })
  } catch {
    return isoString
  }
}

function formatDate(dateString: string): string {
  try {
    const date = new Date(dateString)
    return date.toLocaleDateString('en-AU', { 
      weekday: 'short',
      day: 'numeric',
      month: 'short'
    })
  } catch {
    return dateString
  }
}

export default function Forecast() {
  const [searchParams] = useSearchParams()
  const lat = searchParams.get('lat')
  const lng = searchParams.get('lng')
  const [forecast, setForecast] = useState<ForecastResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!lat || !lng) {
      setError('Missing location coordinates')
      setLoading(false)
      return
    }

    const fetchData = async () => {
      try {
        setLoading(true)
        setError(null)
        const data = await getForecast(parseFloat(lat), parseFloat(lng), 7)
        setForecast(data)
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load forecast')
      } finally {
        setLoading(false)
      }
    }

    fetchData()
  }, [lat, lng])

  if (loading) {
    return (
      <div className="container mx-auto px-4 py-6">
        <div className="text-center py-8">
          <p className="text-gray-600">Loading forecast...</p>
        </div>
      </div>
    )
  }

  if (error || !lat || !lng) {
    return (
      <div className="container mx-auto px-4 py-6">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
          <p className="text-red-800 font-medium mb-2">
            {error || 'Location coordinates are required'}
          </p>
          <p className="text-red-700 text-sm mb-4">
            Please select a location to view the forecast.
          </p>
          <Link to="/locations" className="btn-primary inline-block">
            Browse Locations
          </Link>
        </div>
      </div>
    )
  }

  if (!forecast || !forecast.data) {
    return (
      <div className="container mx-auto px-4 py-6">
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
          <p className="text-yellow-800 font-medium mb-2">No forecast data available</p>
          <Link to="/locations" className="btn-primary inline-block mt-2">
            Browse Locations
          </Link>
        </div>
      </div>
    )
  }

  const { location, forecast: forecastDays } = forecast.data

  return (
    <div className="container mx-auto px-4 py-6 pb-20">
      <header className="mb-6">
        <h1 className="text-2xl font-bold">{location.name}</h1>
        {location.region && (
          <p className="text-gray-600 text-sm mt-1">{location.region}</p>
        )}
        {forecast.data.warning && (
          <div className="mt-3 bg-yellow-50 border border-yellow-200 rounded p-2 text-sm text-yellow-800">
            {forecast.data.warning}
          </div>
        )}
      </header>

      <div className="space-y-4">
        {forecastDays.map((day, idx) => (
          <div key={idx} className="card">
            {/* Date and Score Header */}
            <div className="flex items-center justify-between mb-4 pb-3 border-b">
              <div>
                <h2 className="text-lg font-semibold">{formatDate(day.date)}</h2>
                <p className="text-sm text-gray-500">
                  {idx === 0 ? 'Today' : idx === 1 ? 'Tomorrow' : formatDate(day.date)}
                </p>
              </div>
              <div className="text-right">
                <div className="text-3xl font-bold text-blue-600">
                  {Math.round(day.score)}
                </div>
                <div className="text-xs text-gray-500">/100</div>
              </div>
            </div>

            {/* Weather Conditions */}
            <div className="mb-4">
              <h3 className="text-sm font-semibold text-gray-700 mb-2">Conditions</h3>
              <div className="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <span className="text-gray-600">Temperature:</span>
                  <span className="ml-2 font-medium">
                    {day.weather.temperature_min}° - {day.weather.temperature_max}°
                  </span>
                </div>
                <div>
                  <span className="text-gray-600">Wind:</span>
                  <span className="ml-2 font-medium">{day.weather.wind_speed} km/h</span>
                </div>
                <div>
                  <span className="text-gray-600">Rain:</span>
                  <span className="ml-2 font-medium">{day.weather.precipitation} mm</span>
                </div>
                <div>
                  <span className="text-gray-600">Conditions:</span>
                  <span className="ml-2 font-medium capitalize">{day.weather.conditions}</span>
                </div>
              </div>
            </div>

            {/* Best Bite Windows */}
            {day.best_bite_windows && day.best_bite_windows.length > 0 && (
              <div className="mb-4">
                <h3 className="text-sm font-semibold text-gray-700 mb-2">Best Bite Windows</h3>
                <div className="space-y-2">
                  {day.best_bite_windows.map((window, wIdx) => (
                    <div
                      key={wIdx}
                      className={`p-2 rounded text-sm ${
                        window.quality === 'excellent'
                          ? 'bg-green-50 border border-green-200'
                          : window.quality === 'good'
                          ? 'bg-blue-50 border border-blue-200'
                          : 'bg-gray-50 border border-gray-200'
                      }`}
                    >
                      <div className="flex items-center justify-between mb-1">
                        <span className="font-medium">
                          {formatTime(window.start)} - {formatTime(window.end)}
                        </span>
                        <span
                          className={`text-xs px-2 py-0.5 rounded ${
                            window.quality === 'excellent'
                              ? 'bg-green-200 text-green-800'
                              : window.quality === 'good'
                              ? 'bg-blue-200 text-blue-800'
                              : 'bg-gray-200 text-gray-800'
                          }`}
                        >
                          {window.quality}
                        </span>
                      </div>
                      <p className="text-xs text-gray-600">{window.reason}</p>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Recommended Species */}
            {day.recommended_species && day.recommended_species.length > 0 && (
              <div className="mb-4">
                <h3 className="text-sm font-semibold text-gray-700 mb-2">Recommended Species</h3>
                <div className="flex flex-wrap gap-2">
                  {day.recommended_species.map((species, sIdx) => (
                    <div
                      key={sIdx}
                      className="bg-blue-50 border border-blue-200 rounded-lg px-3 py-2 text-sm"
                    >
                      <div className="font-medium text-blue-900">{species.name}</div>
                      <div className="text-xs text-blue-700 mt-0.5">
                        {Math.round(species.confidence * 100)}% confidence
                      </div>
                      <div className="text-xs text-gray-600 mt-1">{species.why}</div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Gear Suggestions */}
            {day.gear_suggestions && (
              <div className="mb-2">
                <h3 className="text-sm font-semibold text-gray-700 mb-2">Gear Suggestions</h3>
                <div className="bg-gray-50 rounded-lg p-3 text-sm space-y-2">
                  {day.gear_suggestions.bait && day.gear_suggestions.bait.length > 0 && (
                    <div>
                      <span className="font-medium text-gray-700">Bait:</span>
                      <span className="ml-2 text-gray-600">
                        {day.gear_suggestions.bait.join(', ')}
                      </span>
                    </div>
                  )}
                  {day.gear_suggestions.lure && day.gear_suggestions.lure.length > 0 && (
                    <div>
                      <span className="font-medium text-gray-700">Lure:</span>
                      <span className="ml-2 text-gray-600">
                        {day.gear_suggestions.lure.join(', ')}
                      </span>
                    </div>
                  )}
                  {day.gear_suggestions.line_weight && (
                    <div>
                      <span className="font-medium text-gray-700">Line:</span>
                      <span className="ml-2 text-gray-600">{day.gear_suggestions.line_weight}</span>
                    </div>
                  )}
                  {day.gear_suggestions.leader && (
                    <div>
                      <span className="font-medium text-gray-700">Leader:</span>
                      <span className="ml-2 text-gray-600">{day.gear_suggestions.leader}</span>
                    </div>
                  )}
                  {day.gear_suggestions.rig && (
                    <div>
                      <span className="font-medium text-gray-700">Rig:</span>
                      <span className="ml-2 text-gray-600">{day.gear_suggestions.rig}</span>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  )
}

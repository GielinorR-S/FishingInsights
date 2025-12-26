import { useEffect, useState } from 'react'
import { useSearchParams, Link } from 'react-router-dom'
import { getForecast, type ForecastResponse } from '../services/api'

/**
 * Get today's date in Australia/Melbourne timezone (YYYY-MM-DD)
 */
function getTodayInMelbourne(): string {
  const now = new Date()
  const formatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Australia/Melbourne',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  })
  return formatter.format(now)
}

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
  const startParam = searchParams.get('start')
  const [forecast, setForecast] = useState<ForecastResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [selectedDate, setSelectedDate] = useState<string>(startParam || getTodayInMelbourne())
  const [isOffline, setIsOffline] = useState(!navigator.onLine)
  const [isCachedData, setIsCachedData] = useState(false)

  // Offline/online detection
  useEffect(() => {
    const handleOnline = () => setIsOffline(false)
    const handleOffline = () => setIsOffline(true)

    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)

    return () => {
      window.removeEventListener('online', handleOnline)
      window.removeEventListener('offline', handleOffline)
    }
  }, [])

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
        setIsCachedData(false)
        // Use selectedDate or today in Melbourne timezone
        const startDate = selectedDate || getTodayInMelbourne()
        const data = await getForecast(parseFloat(lat), parseFloat(lng), 7, startDate)
        setForecast(data)
        setIsCachedData(false) // Fresh data loaded
      } catch (err) {
        // Check if error is due to network failure
        const isNetworkError = err instanceof TypeError && 
          (err.message.includes('Failed to fetch') || err.message.includes('NetworkError'))
        
        if (isNetworkError && !navigator.onLine) {
          // Offline - keep existing forecast if available, show offline message
          setIsOffline(true)
          if (forecast) {
            setIsCachedData(true) // Show cached data
            setError(null) // Don't show error if we have cached data
          } else {
            setError('offline') // Special error code for offline
          }
        } else {
          // Other error (API error, etc.)
          setError(err instanceof Error ? err.message : 'Failed to load forecast')
          setIsCachedData(false)
        }
      } finally {
        setLoading(false)
      }
    }

    fetchData()
  }, [lat, lng, selectedDate])

  if (loading) {
    return (
      <div className="container mx-auto px-4 py-6 pb-20">
        <div className="card mb-4">
          <div className="animate-pulse">
            <div className="h-6 bg-gray-200 rounded w-1/3 mb-4"></div>
            <div className="h-4 bg-gray-200 rounded w-1/2 mb-2"></div>
            <div className="h-4 bg-gray-200 rounded w-1/4"></div>
          </div>
        </div>
        <div className="text-center py-8">
          <div className="spinner mx-auto mb-3"></div>
          <p className="text-gray-600 text-sm">Loading forecast...</p>
        </div>
      </div>
    )
  }

  // Retry handler
  const handleRetry = () => {
    if (navigator.onLine) {
      setIsOffline(false)
      setError(null)
      // Trigger refetch by updating a dependency
      const startDate = selectedDate || getTodayInMelbourne()
      getForecast(parseFloat(lat!), parseFloat(lng!), 7, startDate)
        .then((data) => {
          setForecast(data)
          setIsCachedData(false)
          setError(null)
        })
        .catch((err) => {
          setError(err instanceof Error ? err.message : 'Failed to load forecast')
        })
    }
  }

  if (error === 'offline' && !forecast) {
    // Offline and no cached data
    return (
      <div className="container mx-auto px-4 py-6 pb-20">
        <div className="banner banner-offline text-center">
          <p className="font-semibold mb-2">You're offline</p>
          <p className="text-sm mb-4 opacity-90">
            Connect to the internet to load a new forecast.
          </p>
          {navigator.onLine && (
            <button onClick={handleRetry} className="btn-primary">
              Retry
            </button>
          )}
        </div>
      </div>
    )
  }

  if (error && error !== 'offline' && !forecast) {
    // Other error (not offline) and no data
    return (
      <div className="container mx-auto px-4 py-6 pb-20">
        <div className="banner banner-error text-center">
          <p className="font-semibold mb-2">
            {error || 'Location coordinates are required'}
          </p>
          <p className="text-sm mb-4 opacity-90">
            {!lat || !lng ? 'Please select a location to view the forecast.' : 'Failed to load forecast. Please try again.'}
          </p>
          {!lat || !lng ? (
            <Link to="/locations" className="btn-primary inline-block">
              Browse Locations
            </Link>
          ) : (
            <button onClick={handleRetry} className="btn-primary">
              Retry
            </button>
          )}
        </div>
      </div>
    )
  }

  if (!lat || !lng) {
    return (
      <div className="container mx-auto px-4 py-6 pb-20">
        <div className="banner banner-error text-center">
          <p className="font-semibold mb-2">Location coordinates are required</p>
          <p className="text-sm mb-4 opacity-90">
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
      <div className="container mx-auto px-4 py-6 pb-20">
        <div className="banner banner-warning text-center">
          <p className="font-semibold mb-2">No forecast data available</p>
          <Link to="/locations" className="btn-primary inline-block mt-2">
            Browse Locations
          </Link>
        </div>
      </div>
    )
  }

  const { location, forecast: forecastDays } = forecast.data

  // Get the actual start date from the forecast (first day's date)
  const actualStartDate = forecastDays.length > 0 ? forecastDays[0].date : selectedDate
  const startDateLabel = formatDate(actualStartDate)

  return (
    <div className="container mx-auto px-4 py-6 pb-20">
      {/* Offline banner */}
      {isOffline && (
        <div className="banner banner-offline mb-4">
          <div className="flex items-start justify-between gap-3">
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-1">
                <span className="badge badge-offline">Offline</span>
                {isCachedData && <span className="badge badge-cached">Cached</span>}
              </div>
              <p className="font-medium text-sm">
                {isCachedData ? 'Showing last saved forecast' : "You're offline"}
              </p>
              <p className="text-xs mt-1 opacity-90">
                {isCachedData 
                  ? 'Connect to the internet to load a new forecast.'
                  : 'Connect to the internet to load the forecast.'}
              </p>
            </div>
            {navigator.onLine && (
              <button onClick={handleRetry} className="btn-primary text-sm whitespace-nowrap">
                Retry
              </button>
            )}
          </div>
        </div>
      )}

      <header className="page-header">
        <div className="flex items-start justify-between mb-4">
          <div>
            <h1 className="page-title">{location.name}</h1>
            {location.region && (
              <p className="page-subtitle">{location.region}</p>
            )}
          </div>
        </div>
        
        {/* Date Picker */}
        <div className="mt-4">
          <label htmlFor="date-picker" className="block text-xs font-medium text-gray-700 mb-2 uppercase tracking-wide">
            Forecast Start Date
          </label>
          <input
            id="date-picker"
            type="date"
            value={selectedDate}
            onChange={(e) => setSelectedDate(e.target.value)}
            min={getTodayInMelbourne()}
            className="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
          <p className="text-xs text-gray-500 mt-1.5">
            Showing forecast from {startDateLabel}
          </p>
        </div>
        
        {forecast.data.warning && (
          <div className="mt-4 banner banner-warning text-sm">
            {forecast.data.warning}
          </div>
        )}
      </header>

      <div className="space-y-4">
        {forecastDays.map((day, idx) => (
          <div key={idx} className="card">
            {/* Date and Score Header */}
            <div className="flex items-center justify-between mb-5 pb-4 border-b border-gray-200">
              <div>
                <h2 className="text-lg font-bold text-gray-900">{formatDate(day.date)}</h2>
                <p className="text-sm text-gray-500 mt-0.5">
                  {idx === 0 ? 'Today' : idx === 1 ? 'Tomorrow' : formatDate(day.date)}
                </p>
              </div>
              <div className="flex items-baseline gap-1">
                <span className="badge-score text-lg">
                  {Math.round(day.score)}
                </span>
                <span className="text-xs text-gray-400 font-medium">/100</span>
              </div>
            </div>

            {/* Weather Conditions */}
            <div className="mb-5">
              <h3 className="section-heading">Conditions</h3>
              <div className="grid grid-cols-2 gap-3 text-sm">
                <div className="bg-gray-50 rounded-lg p-3">
                  <div className="text-xs text-gray-500 mb-1">Temperature</div>
                  <div className="font-semibold text-gray-900">
                    {day.weather.temperature_min}° - {day.weather.temperature_max}°
                  </div>
                </div>
                <div className="bg-gray-50 rounded-lg p-3">
                  <div className="text-xs text-gray-500 mb-1">Wind</div>
                  <div className="font-semibold text-gray-900">{day.weather.wind_speed} km/h</div>
                </div>
                <div className="bg-gray-50 rounded-lg p-3">
                  <div className="text-xs text-gray-500 mb-1">Rain</div>
                  <div className="font-semibold text-gray-900">{day.weather.precipitation} mm</div>
                </div>
                <div className="bg-gray-50 rounded-lg p-3">
                  <div className="text-xs text-gray-500 mb-1">Sky</div>
                  <div className="font-semibold text-gray-900 capitalize">{day.weather.conditions}</div>
                </div>
              </div>
            </div>

            {/* Best Bite Windows */}
            {day.best_bite_windows && day.best_bite_windows.length > 0 && (
              <div className="mb-5">
                <h3 className="section-heading">Best Bite Windows</h3>
                <div className="space-y-2.5">
                  {day.best_bite_windows.map((window, wIdx) => (
                    <div
                      key={wIdx}
                      className={`p-3 rounded-lg border ${
                        window.quality === 'excellent'
                          ? 'bg-green-50 border-green-200'
                          : window.quality === 'good'
                          ? 'bg-blue-50 border-blue-200'
                          : 'bg-gray-50 border-gray-200'
                      }`}
                    >
                      <div className="flex items-center justify-between mb-1.5">
                        <span className="font-semibold text-sm text-gray-900">
                          {formatTime(window.start)} - {formatTime(window.end)}
                        </span>
                        <span
                          className={`badge ${
                            window.quality === 'excellent'
                              ? 'badge-quality-excellent'
                              : window.quality === 'good'
                              ? 'badge-quality-good'
                              : 'badge-quality-fair'
                          }`}
                        >
                          {window.quality}
                        </span>
                      </div>
                      <p className="text-xs text-gray-600 leading-relaxed">{window.reason}</p>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Recommended Species */}
            {day.recommended_species && day.recommended_species.length > 0 && (
              <div className="mb-5">
                <h3 className="section-heading">Recommended Species</h3>
                <div className="flex flex-wrap gap-2.5">
                  {day.recommended_species.map((species, sIdx) => (
                    <div
                      key={sIdx}
                      className="bg-primary-50 border border-primary-200 rounded-lg px-3 py-2.5 flex-1 min-w-[140px]"
                    >
                      <div className="font-semibold text-primary-900 text-sm mb-1">{species.name}</div>
                      <div className="text-xs text-primary-700 mb-1.5">
                        {Math.round(species.confidence * 100)}% confidence
                      </div>
                      <div className="text-xs text-gray-600 leading-relaxed">{species.why}</div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Gear Suggestions */}
            {day.gear_suggestions && (
              <div>
                <h3 className="section-heading">Gear Suggestions</h3>
                <div className="bg-gray-50 rounded-lg p-4 text-sm space-y-2.5">
                  {day.gear_suggestions.bait && day.gear_suggestions.bait.length > 0 && (
                    <div className="flex">
                      <span className="font-semibold text-gray-700 w-16 flex-shrink-0">Bait:</span>
                      <span className="text-gray-600 flex-1">
                        {day.gear_suggestions.bait.join(', ')}
                      </span>
                    </div>
                  )}
                  {day.gear_suggestions.lure && day.gear_suggestions.lure.length > 0 && (
                    <div className="flex">
                      <span className="font-semibold text-gray-700 w-16 flex-shrink-0">Lure:</span>
                      <span className="text-gray-600 flex-1">
                        {day.gear_suggestions.lure.join(', ')}
                      </span>
                    </div>
                  )}
                  {day.gear_suggestions.line_weight && (
                    <div className="flex">
                      <span className="font-semibold text-gray-700 w-16 flex-shrink-0">Line:</span>
                      <span className="text-gray-600 flex-1">{day.gear_suggestions.line_weight}</span>
                    </div>
                  )}
                  {day.gear_suggestions.leader && (
                    <div className="flex">
                      <span className="font-semibold text-gray-700 w-16 flex-shrink-0">Leader:</span>
                      <span className="text-gray-600 flex-1">{day.gear_suggestions.leader}</span>
                    </div>
                  )}
                  {day.gear_suggestions.rig && (
                    <div className="flex">
                      <span className="font-semibold text-gray-700 w-16 flex-shrink-0">Rig:</span>
                      <span className="text-gray-600 flex-1">{day.gear_suggestions.rig}</span>
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

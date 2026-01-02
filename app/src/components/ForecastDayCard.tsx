import React from 'react'

interface ForecastDay {
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
    }>
  }
  best_bite_windows: Array<{
    start: string
    end: string
    quality: 'excellent' | 'good' | 'fair'
    reasons: string[]
  }>
  recommended_species: Array<{
    id: number
    name: string
    confidence: number
    why: string
  }>
  gear_suggestions: {
    bait?: string[]
    lure?: string[]
    line_weight?: string
    leader?: string
    rig?: string
  }
  reasons: Array<{
    title: string
    detail: string
    contribution_points: number
    severity: 'positive' | 'neutral' | 'negative'
    category: string
  }>
}

interface ForecastDayCardProps {
  day: ForecastDay
  index: number
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

function formatDateLong(dateString: string): string {
  try {
    const date = new Date(dateString)
    return date.toLocaleDateString('en-AU', { 
      weekday: 'long',
      day: 'numeric',
      month: 'long'
    })
  } catch {
    return dateString
  }
}

const ForecastDayCard: React.FC<ForecastDayCardProps> = React.memo(({ day, index }) => {
  return (
    <div className="card">
      {/* Day Header with Score */}
      <div className="flex items-center justify-between mb-4 pb-4 border-b border-gray-200">
        <div className="flex-1">
          <h2 className="text-lg font-bold text-gray-900">{formatDate(day.date)}</h2>
          <p className="text-sm text-gray-600 mt-0.5">
            {index === 0 ? 'Today' : index === 1 ? 'Tomorrow' : formatDateLong(day.date)}
          </p>
        </div>
        <div className="flex items-baseline gap-1.5">
          <span className="badge-score">
            {Math.round(day.score)}
          </span>
          <span className="text-xs text-gray-500 font-medium">/100</span>
        </div>
      </div>

      {/* Quick Summary - Weather Conditions */}
      <div className="mb-4">
        <h3 className="section-heading">Conditions</h3>
        <div className="grid grid-cols-2 gap-2.5">
          <div className="bg-gray-50 rounded-lg p-3">
            <div className="text-xs text-gray-600 mb-1">Temperature</div>
            <div className="font-semibold text-gray-900 text-sm">
              {day.weather.temperature_min}° - {day.weather.temperature_max}°
            </div>
          </div>
          <div className="bg-gray-50 rounded-lg p-3">
            <div className="text-xs text-gray-600 mb-1">Wind</div>
            <div className="font-semibold text-gray-900 text-sm">
              {day.weather.wind_speed} km/h
            </div>
          </div>
          <div className="bg-gray-50 rounded-lg p-3">
            <div className="text-xs text-gray-600 mb-1">Rain</div>
            <div className="font-semibold text-gray-900 text-sm">
              {day.weather.precipitation > 0 ? `${day.weather.precipitation}mm` : 'None'}
            </div>
          </div>
          <div className="bg-gray-50 rounded-lg p-3">
            <div className="text-xs text-gray-600 mb-1">Conditions</div>
            <div className="font-semibold text-gray-900 text-sm capitalize">
              {day.weather.conditions}
            </div>
          </div>
        </div>
      </div>

      {/* Best Bite Windows */}
      {day.best_bite_windows && day.best_bite_windows.length > 0 && (
        <div className="mb-4">
          <h3 className="section-heading">Best Bite Windows</h3>
          <div className="space-y-2">
            {day.best_bite_windows.map((window, winIdx) => (
              <div key={winIdx} className="bg-primary-50 rounded-lg p-3 border border-primary-100">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-sm font-semibold text-gray-900">
                    {formatTime(window.start)} - {formatTime(window.end)}
                  </span>
                  <span className={`badge badge-quality-${window.quality}`}>
                    {window.quality}
                  </span>
                </div>
                {window.reasons && window.reasons.length > 0 && (
                  <ul className="text-xs text-gray-600 space-y-0.5 list-disc list-inside">
                    {window.reasons.map((reason, reasonIdx) => (
                      <li key={reasonIdx}>{reason}</li>
                    ))}
                  </ul>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Tides */}
      {day.tides && day.tides.events && day.tides.events.length > 0 && (
        <div className="mb-4">
          <h3 className="section-heading">Tides</h3>
          <div className="space-y-2">
            {day.tides.events.map((event, tideIdx) => (
              <div key={tideIdx} className="flex items-center justify-between bg-gray-50 rounded-lg p-2.5">
                <div className="flex items-center gap-2">
                  <span className={`text-xs font-semibold px-2 py-0.5 rounded ${
                    event.type === 'high' ? 'bg-blue-100 text-blue-800' : 'bg-gray-200 text-gray-700'
                  }`}>
                    {event.type === 'high' ? 'High' : 'Low'}
                  </span>
                  <span className="text-sm text-gray-900">{formatTime(event.time)}</span>
                </div>
                <span className="text-xs text-gray-600">{event.height.toFixed(2)}m</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Recommended Species */}
      {day.recommended_species && day.recommended_species.length > 0 && (
        <div className="mb-4">
          <h3 className="section-heading">Recommended Species</h3>
          <div className="flex flex-wrap gap-2">
            {day.recommended_species.map((species) => (
              <div key={species.id} className="bg-green-50 rounded-lg px-3 py-2 border border-green-100">
                <div className="flex items-center justify-between gap-2 mb-1">
                  <span className="text-sm font-semibold text-gray-900">{species.name}</span>
                  <span className="text-xs font-medium text-green-700">{species.confidence}%</span>
                </div>
                {species.why && (
                  <p className="text-xs text-gray-600">{species.why}</p>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Gear Suggestions */}
      {day.gear_suggestions && (
        <div className="mb-4">
          <h3 className="section-heading">Gear Suggestions</h3>
          <div className="bg-gray-50 rounded-lg p-3 space-y-2 text-sm">
            {day.gear_suggestions.bait && day.gear_suggestions.bait.length > 0 && (
              <div>
                <span className="font-semibold text-gray-700">Bait: </span>
                <span className="text-gray-600">{day.gear_suggestions.bait.join(', ')}</span>
              </div>
            )}
            {day.gear_suggestions.lure && day.gear_suggestions.lure.length > 0 && (
              <div>
                <span className="font-semibold text-gray-700">Lure: </span>
                <span className="text-gray-600">{day.gear_suggestions.lure.join(', ')}</span>
              </div>
            )}
            {day.gear_suggestions.line_weight && (
              <div>
                <span className="font-semibold text-gray-700">Line: </span>
                <span className="text-gray-600">{day.gear_suggestions.line_weight}</span>
              </div>
            )}
            {day.gear_suggestions.leader && (
              <div>
                <span className="font-semibold text-gray-700">Leader: </span>
                <span className="text-gray-600">{day.gear_suggestions.leader}</span>
              </div>
            )}
            {day.gear_suggestions.rig && (
              <div>
                <span className="font-semibold text-gray-700">Rig: </span>
                <span className="text-gray-600">{day.gear_suggestions.rig}</span>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Reasons */}
      {day.reasons && day.reasons.length > 0 && (
        <div>
          <h3 className="section-heading">Why This Score</h3>
          <div className="space-y-2">
            {day.reasons.map((reason, reasonIdx) => (
              <div key={reasonIdx} className="bg-gray-50 rounded-lg p-3">
                <div className="flex items-start justify-between mb-1">
                  <h4 className="text-sm font-semibold text-gray-900">{reason.title}</h4>
                  {reason.contribution_points !== 0 && (
                    <span className={`text-xs font-medium px-2 py-0.5 rounded ${
                      reason.contribution_points > 0 
                        ? 'bg-green-100 text-green-800' 
                        : 'bg-red-100 text-red-800'
                    }`}>
                      {reason.contribution_points > 0 ? '+' : ''}{reason.contribution_points}
                    </span>
                  )}
                </div>
                <p className="text-xs text-gray-600">{reason.detail}</p>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
})

ForecastDayCard.displayName = 'ForecastDayCard'

export default ForecastDayCard


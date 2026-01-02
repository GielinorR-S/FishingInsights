import { useEffect, useState, useMemo, useCallback, useRef } from "react";
import { useSearchParams, Link } from "react-router-dom";
import { getForecast, type ForecastResponse } from "../services/api";
import { useFavourites } from "../contexts/FavouritesContext";
import { usePreferences } from "../contexts/PreferencesContext";
import { useTargetSpecies } from "../contexts/TargetSpeciesContext";
import SpeciesSelector from "../components/SpeciesSelector";
import {
  StarIcon,
  RefreshIcon,
  OfflineIcon,
  AlertCircleIcon,
  BarChartIcon,
} from "../components/icons";

/**
 * Generate a unique key for a location (used for favorites)
 */
function getLocationKey(lat: number, lng: number): string {
  return `${lat.toFixed(4)}:${lng.toFixed(4)}`;
}

/**
 * Get today's date in Australia/Melbourne timezone (YYYY-MM-DD)
 */
function getTodayInMelbourne(): string {
  const now = new Date();
  const formatter = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Australia/Melbourne",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
  return formatter.format(now);
}

function formatTime(isoString: string): string {
  try {
    const date = new Date(isoString);
    return date.toLocaleTimeString("en-AU", {
      hour: "2-digit",
      minute: "2-digit",
      hour12: true,
    });
  } catch {
    return isoString;
  }
}

function formatDate(dateString: string): string {
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString("en-AU", {
      weekday: "short",
      day: "numeric",
      month: "short",
    });
  } catch {
    return dateString;
  }
}

function formatDateLong(dateString: string): string {
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString("en-AU", {
      weekday: "long",
      day: "numeric",
      month: "long",
    });
  } catch {
    return dateString;
  }
}

/**
 * Loading Skeleton Component
 */
function LoadingSkeleton() {
  return (
    <div className="container">
      {/* Header Skeleton */}
      <div className="card mb-4">
        <div className="animate-pulse space-y-4">
          <div className="h-8 bg-gray-200 rounded w-2/3"></div>
          <div className="h-4 bg-gray-200 rounded w-1/2"></div>
          <div className="h-10 bg-gray-200 rounded"></div>
        </div>
      </div>

      {/* Day Cards Skeleton */}
      {[1, 2, 3].map((i) => (
        <div key={i} className="card mb-4">
          <div className="animate-pulse space-y-4">
            <div className="flex justify-between items-center">
              <div className="h-6 bg-gray-200 rounded w-1/3"></div>
              <div className="h-12 w-12 bg-gray-200 rounded-full"></div>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="h-20 bg-gray-200 rounded"></div>
              <div className="h-20 bg-gray-200 rounded"></div>
              <div className="h-20 bg-gray-200 rounded"></div>
              <div className="h-20 bg-gray-200 rounded"></div>
            </div>
            <div className="h-24 bg-gray-200 rounded"></div>
            <div className="h-16 bg-gray-200 rounded"></div>
          </div>
        </div>
      ))}
    </div>
  );
}

export default function Forecast() {
  const { isFavourite, toggleFavourite } = useFavourites();
  const { lastDate, setLastDate, setLastLocation } = usePreferences();
  const { targetSpecies } = useTargetSpecies();
  const [searchParams] = useSearchParams();
  const lat = searchParams.get("lat");
  const lng = searchParams.get("lng");
  const startParam = searchParams.get("start");
  const [forecast, setForecast] = useState<ForecastResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedDate, setSelectedDate] = useState<string>(
    startParam || lastDate || getTodayInMelbourne()
  );
  const [isOffline, setIsOffline] = useState(!navigator.onLine);
  const [isCachedData, setIsCachedData] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);

  // Save location when lat/lng are available
  useEffect(() => {
    if (lat && lng) {
      setLastLocation(parseFloat(lat), parseFloat(lng));
    }
  }, [lat, lng, setLastLocation]);

  // Save date when it changes
  useEffect(() => {
    if (selectedDate) {
      setLastDate(selectedDate);
    }
  }, [selectedDate, setLastDate]);

  // Offline/online detection
  useEffect(() => {
    const handleOnline = () => setIsOffline(false);
    const handleOffline = () => setIsOffline(true);

    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);

    return () => {
      window.removeEventListener("online", handleOnline);
      window.removeEventListener("offline", handleOffline);
    };
  }, []);

  // Track last successful request to avoid unnecessary refetches
  const lastRequestRef = useRef<string | null>(null);

  const fetchData = useCallback(
    async (showRefreshing = false, forceRefresh = false) => {
      if (!lat || !lng) {
        setError("Missing location coordinates");
        setLoading(false);
        return;
      }

      const startDate = selectedDate || getTodayInMelbourne();
      const requestKey = `${lat}:${lng}:${startDate}`;

      // Avoid refetching if we already have data for this exact request (unless forced)
      if (
        !forceRefresh &&
        !showRefreshing &&
        lastRequestRef.current === requestKey &&
        forecast
      ) {
        return;
      }

      try {
        if (showRefreshing) {
          setIsRefreshing(true);
        } else {
          setLoading(true);
        }
        setError(null);
        setIsCachedData(false);

        const data = await getForecast(
          parseFloat(lat),
          parseFloat(lng),
          7,
          startDate,
          targetSpecies.length > 0 ? targetSpecies : undefined
        );
        setForecast(data);
        lastRequestRef.current = requestKey;
        setIsCachedData(data.data.cached || false);
        setIsOffline(false);
      } catch (err) {
        const isNetworkError =
          err instanceof TypeError &&
          (err.message.includes("Failed to fetch") ||
            err.message.includes("NetworkError"));

        if (isNetworkError && !navigator.onLine) {
          setIsOffline(true);
          if (forecast) {
            setIsCachedData(true);
            setError(null);
          } else {
            setError("offline");
          }
        } else {
          setError(
            err instanceof Error ? err.message : "Failed to load forecast"
          );
          setIsCachedData(false);
        }
      } finally {
        setLoading(false);
        setIsRefreshing(false);
      }
    },
    [lat, lng, selectedDate, forecast, targetSpecies]
  );

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleRetry = useCallback(() => {
    if (navigator.onLine) {
      fetchData(false, true);
    }
  }, [fetchData]);

  const handleRefresh = useCallback(() => {
    fetchData(true, true);
  }, [fetchData]);

  // Memoize location key and starred state
  const locationKey = useMemo(() => {
    return lat && lng ? getLocationKey(parseFloat(lat), parseFloat(lng)) : null;
  }, [lat, lng]);

  const isStarred = useMemo(() => {
    return locationKey ? isFavourite(locationKey) : false;
  }, [locationKey, isFavourite]);

  // Loading state
  if (loading && !forecast) {
    return <LoadingSkeleton />;
  }

  // Error states
  if (error === "offline" && !forecast) {
    return (
      <div className="container">
        <div className="card text-center py-8">
          <OfflineIcon
            className="w-16 h-16 mx-auto mb-4 text-gray-300"
            size={64}
          />
          <h3 className="text-lg font-semibold text-gray-900 mb-2">
            You're offline
          </h3>
          <p className="text-sm text-gray-600 mb-4">
            We need an internet connection to load the latest fishing forecast.
          </p>
          <div className="space-y-2">
            <p className="text-xs font-medium text-gray-700">
              What to do next:
            </p>
            <ul className="text-xs text-gray-600 space-y-1 list-disc list-inside max-w-sm mx-auto">
              <li>Check your Wi-Fi or mobile data connection</li>
              <li>Once connected, tap Retry to load the forecast</li>
              <li>You can still browse saved locations while offline</li>
            </ul>
          </div>
          {navigator.onLine && (
            <button onClick={handleRetry} className="btn-primary mt-4">
              Retry
            </button>
          )}
        </div>
      </div>
    );
  }

  if (error && error !== "offline" && !forecast) {
    return (
      <div className="container">
        <div className="card text-center py-8">
          <AlertCircleIcon
            className="w-16 h-16 mx-auto mb-4 text-gray-300"
            size={64}
          />
          <h3 className="text-lg font-semibold text-gray-900 mb-2">
            {!lat || !lng ? "Location needed" : "Unable to load forecast"}
          </h3>
          <p className="text-sm text-gray-600 mb-4">
            {!lat || !lng
              ? "We need to know which fishing spot you'd like to see a forecast for."
              : "We couldn't load the forecast right now. This usually fixes itself quickly."}
          </p>
          <div className="space-y-2">
            <p className="text-xs font-medium text-gray-700">
              What to do next:
            </p>
            <ul className="text-xs text-gray-600 space-y-1 list-disc list-inside max-w-sm mx-auto">
              {!lat || !lng ? (
                <>
                  <li>Browse our list of fishing locations</li>
                  <li>Select a location to see its 7-day forecast</li>
                  <li>You can also use your current location</li>
                </>
              ) : (
                <>
                  <li>Check your internet connection</li>
                  <li>Wait a moment and try again</li>
                  <li>If the problem persists, try a different location</li>
                </>
              )}
            </ul>
          </div>
          {!lat || !lng ? (
            <Link to="/locations" className="btn-primary inline-block mt-4">
              Browse Locations
            </Link>
          ) : (
            <button onClick={handleRetry} className="btn-primary mt-4">
              Try Again
            </button>
          )}
        </div>
      </div>
    );
  }

  if (!lat || !lng) {
    return (
      <div className="container">
        <div className="card text-center py-8">
          <svg
            className="w-16 h-16 mx-auto mb-4 text-gray-300"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
            />
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
            />
          </svg>
          <h3 className="text-lg font-semibold text-gray-900 mb-2">
            Select a fishing location
          </h3>
          <p className="text-sm text-gray-600 mb-4">
            Choose a location to see its 7-day fishing forecast with scores,
            bite windows, and recommendations.
          </p>
          <div className="space-y-2">
            <p className="text-xs font-medium text-gray-700">
              What to do next:
            </p>
            <ul className="text-xs text-gray-600 space-y-1 list-disc list-inside max-w-sm mx-auto">
              <li>Browse our curated list of Victorian fishing spots</li>
              <li>Search by name, region, or type</li>
              <li>Star your favorite locations for quick access</li>
            </ul>
          </div>
          <Link to="/locations" className="btn-primary inline-block mt-4">
            Browse Locations
          </Link>
        </div>
      </div>
    );
  }

  if (!forecast || !forecast.data) {
    return (
      <div className="container">
        <div className="card text-center py-8">
          <BarChartIcon
            className="w-16 h-16 mx-auto mb-4 text-gray-300"
            size={64}
          />
          <h3 className="text-lg font-semibold text-gray-900 mb-2">
            No forecast data available
          </h3>
          <p className="text-sm text-gray-600 mb-4">
            We couldn't retrieve the forecast for this location right now.
          </p>
          <div className="space-y-2">
            <p className="text-xs font-medium text-gray-700">
              What to do next:
            </p>
            <ul className="text-xs text-gray-600 space-y-1 list-disc list-inside max-w-sm mx-auto">
              <li>Try refreshing the page</li>
              <li>Select a different location</li>
              <li>Check your internet connection</li>
            </ul>
          </div>
          <div className="flex gap-2 justify-center mt-4">
            <button onClick={handleRefresh} className="btn-secondary">
              Refresh
            </button>
            <Link to="/locations" className="btn-primary">
              Browse Locations
            </Link>
          </div>
        </div>
      </div>
    );
  }

  const { location, forecast: forecastDays } = forecast.data;
  const actualStartDate =
    forecastDays.length > 0 ? forecastDays[0].date : selectedDate;
  const dateRangeEnd =
    forecastDays.length > 0
      ? forecastDays[forecastDays.length - 1].date
      : actualStartDate;

  return (
    <div className="container">
      {/* Offline/Cached Banner */}
      {(isOffline || isCachedData) && (
        <div className="banner banner-offline mb-4">
          <div className="flex items-start justify-between gap-3">
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-1">
                {isOffline && (
                  <span className="badge badge-offline">Offline</span>
                )}
                {isCachedData && (
                  <span className="badge badge-cached">Cached</span>
                )}
              </div>
              <p className="font-medium text-sm">
                {isCachedData
                  ? "Showing last saved forecast"
                  : "You're offline"}
              </p>
              <p className="text-xs mt-1">
                {isCachedData
                  ? "Connect to the internet to load a new forecast."
                  : "Connect to the internet to load the forecast."}
              </p>
            </div>
            {navigator.onLine && (
              <button
                onClick={handleRetry}
                className="btn-primary text-sm whitespace-nowrap"
                disabled={isRefreshing}
              >
                {isRefreshing ? "Loading..." : "Retry"}
              </button>
            )}
          </div>
        </div>
      )}

      {/* Header */}
      <div className="card mb-4">
        <div className="flex items-start justify-between gap-3 mb-4">
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2 mb-1">
              <h1 className="page-title truncate">{location.name}</h1>
              {locationKey && (
                <button
                  onClick={() => toggleFavourite(locationKey)}
                  className="p-1 rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0"
                  aria-label={
                    isStarred ? "Remove from favourites" : "Add to favourites"
                  }
                >
                  <StarIcon
                    className={`w-5 h-5 transition-colors ${
                      isStarred
                        ? "fill-yellow-400 text-yellow-400"
                        : "text-gray-400 hover:text-yellow-400"
                    }`}
                    filled={isStarred}
                  />
                </button>
              )}
            </div>
            {location.region && (
              <p className="page-subtitle">{location.region}</p>
            )}
          </div>
          <button
            onClick={handleRefresh}
            disabled={isRefreshing || isOffline}
            className="btn-ghost flex-shrink-0"
            aria-label="Refresh forecast"
          >
            <RefreshIcon
              className={`w-5 h-5 ${isRefreshing ? "animate-spin" : ""}`}
            />
          </button>
        </div>

        {/* Date Range */}
        <div className="space-y-3">
          <div>
            <label htmlFor="date-picker" className="section-heading block mb-2">
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
          </div>
          <p className="text-xs text-gray-600">
            Showing {formatDateLong(actualStartDate)} to{" "}
            {formatDateLong(dateRangeEnd)}
          </p>
        </div>

        {forecast.data.warning && (
          <div className="mt-4 banner banner-warning text-sm">
            {forecast.data.warning}
          </div>
        )}
      </div>

      {/* Target Species Selector */}
      <SpeciesSelector
        locationRegion={location.region || null}
        locationState="VIC"
        className="mb-4"
      />

      {/* Forecast Days */}
      <div className="space-y-4">
        {forecastDays.map((day, idx) => (
          <div
            key={idx}
            className="card border-l-4 border-l-primary-500 hover:border-l-primary-600 transition-colors"
          >
            {/* Day Header with Score */}
            <div className="flex items-center justify-between mb-5 pb-5 border-b-2 border-gray-200">
              <div className="flex-1">
                <h2 className="text-xl font-bold text-gray-900 mb-1">
                  {formatDate(day.date)}
                </h2>
                <p className="text-sm text-gray-600 font-medium">
                  {idx === 0
                    ? "Today"
                    : idx === 1
                    ? "Tomorrow"
                    : formatDateLong(day.date)}
                </p>
              </div>
              <div className="flex items-baseline gap-2">
                <span className="badge-score text-lg px-4 py-2">
                  {Math.round(day.score)}
                </span>
                <span className="text-sm text-gray-500 font-semibold">
                  /100
                </span>
              </div>
            </div>

            {/* Quick Summary - Weather Conditions */}
            <div className="mb-4 pb-4 border-b border-gray-100">
              <h3 className="section-heading">Conditions</h3>
              <div className="grid grid-cols-2 gap-2.5">
                <div className="bg-gray-50 rounded-lg p-3">
                  <div className="text-xs text-gray-600 mb-1">Temperature</div>
                  <div className="font-semibold text-gray-900 text-sm">
                    {day.weather.temperature_min}° -{" "}
                    {day.weather.temperature_max}°
                  </div>
                </div>
                <div className="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-4 border border-gray-200 shadow-sm">
                  <div className="text-xs text-gray-600 mb-1.5 font-medium">
                    Wind
                  </div>
                  <div className="font-bold text-gray-900 text-base">
                    {day.weather.wind_speed} km/h
                  </div>
                </div>
                <div className="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-4 border border-gray-200 shadow-sm">
                  <div className="text-xs text-gray-600 mb-1.5 font-medium">
                    Rain
                  </div>
                  <div className="font-bold text-gray-900 text-base">
                    {day.weather.precipitation} mm
                  </div>
                </div>
                <div className="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-4 border border-gray-200 shadow-sm">
                  <div className="text-xs text-gray-600 mb-1.5 font-medium">
                    Sky
                  </div>
                  <div className="font-bold text-gray-900 text-base capitalize">
                    {day.weather.conditions}
                  </div>
                </div>
              </div>
            </div>

            {/* Best Bite Windows */}
            {day.best_bite_windows && day.best_bite_windows.length > 0 && (
              <div className="mb-4 pb-4 border-b border-gray-100">
                <h3 className="section-heading">Best Bite Windows</h3>
                <div className="space-y-2.5">
                  {day.best_bite_windows.map((window, wIdx) => (
                    <div
                      key={wIdx}
                      className={`p-3 rounded-lg border ${
                        window.quality === "excellent"
                          ? "bg-green-50 border-green-200"
                          : window.quality === "good"
                          ? "bg-blue-50 border-blue-200"
                          : "bg-gray-50 border-gray-200"
                      }`}
                    >
                      <div className="flex items-center justify-between mb-1.5">
                        <span className="font-semibold text-sm text-gray-900">
                          {formatTime(window.start)} - {formatTime(window.end)}
                        </span>
                        <span
                          className={`badge ${
                            window.quality === "excellent"
                              ? "badge-quality-excellent"
                              : window.quality === "good"
                              ? "badge-quality-good"
                              : "badge-quality-fair"
                          }`}
                        >
                          {window.quality}
                        </span>
                      </div>
                      <p className="text-xs text-gray-700 leading-relaxed">
                        {window.reason}
                      </p>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Tides */}
            {day.tides && day.tides.events && day.tides.events.length > 0 && (
              <div className="mb-4 pb-4 border-b border-gray-100">
                <h3 className="section-heading">Tides</h3>
                <div className="space-y-2">
                  {day.tides.events.slice(0, 4).map((tide, tIdx) => (
                    <div
                      key={tIdx}
                      className="flex items-center justify-between text-sm bg-gray-50 rounded-lg p-2.5"
                    >
                      <div className="flex items-center gap-2">
                        <span
                          className={`badge ${
                            tide.type === "high"
                              ? "bg-blue-100 text-blue-900"
                              : "bg-gray-100 text-gray-900"
                          }`}
                        >
                          {tide.type === "high" ? "High" : "Low"}
                        </span>
                        <span className="text-gray-700">
                          {formatTime(tide.time)}
                        </span>
                      </div>
                      <span className="text-gray-600 font-medium">
                        {tide.height.toFixed(1)}m
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Recommended Species */}
            {day.recommended_species && day.recommended_species.length > 0 && (
              <div className="mb-4 pb-4 border-b border-gray-100">
                <h3 className="section-heading">Recommended Species</h3>
                <div className="space-y-2.5">
                  {day.recommended_species.map((species, sIdx) => (
                    <div
                      key={sIdx}
                      className="bg-primary-50 border border-primary-200 rounded-lg p-3"
                    >
                      <div className="flex items-start justify-between mb-1.5">
                        <div className="font-semibold text-primary-900 text-sm">
                          {species.name}
                        </div>
                        <span className="badge bg-primary-200 text-primary-900 text-xs">
                          {Math.round(species.confidence * 100)}%
                        </span>
                      </div>
                      <p className="text-xs text-gray-700 leading-relaxed">
                        {species.why}
                      </p>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Gear Suggestions */}
            {day.gear_suggestions && (
              <div>
                <h3 className="section-heading mb-5">Recommended Tackle</h3>
                <div className="bg-gradient-to-br from-gray-50 to-blue-50 rounded-xl p-5 border-2 border-gray-200 shadow-md space-y-3 text-sm">
                  {day.gear_suggestions.bait &&
                    day.gear_suggestions.bait.length > 0 && (
                      <div className="flex gap-2">
                        <span className="font-semibold text-gray-700 w-16 flex-shrink-0">
                          Bait:
                        </span>
                        <span className="text-gray-700 flex-1">
                          {day.gear_suggestions.bait.join(", ")}
                        </span>
                      </div>
                    )}
                  {day.gear_suggestions.lure &&
                    day.gear_suggestions.lure.length > 0 && (
                      <div className="flex gap-2">
                        <span className="font-semibold text-gray-700 w-16 flex-shrink-0">
                          Lure:
                        </span>
                        <span className="text-gray-700 flex-1">
                          {day.gear_suggestions.lure.join(", ")}
                        </span>
                      </div>
                    )}
                  {day.gear_suggestions.line_weight && (
                    <div className="flex gap-2">
                      <span className="font-semibold text-gray-700 w-16 flex-shrink-0">
                        Line:
                      </span>
                      <span className="text-gray-700 flex-1">
                        {day.gear_suggestions.line_weight}
                      </span>
                    </div>
                  )}
                  {day.gear_suggestions.leader && (
                    <div className="flex gap-2">
                      <span className="font-semibold text-gray-700 w-16 flex-shrink-0">
                        Leader:
                      </span>
                      <span className="text-gray-700 flex-1">
                        {day.gear_suggestions.leader}
                      </span>
                    </div>
                  )}
                  {day.gear_suggestions.rig && (
                    <div className="flex gap-2">
                      <span className="font-semibold text-gray-700 w-16 flex-shrink-0">
                        Rig:
                      </span>
                      <span className="text-gray-700 flex-1">
                        {day.gear_suggestions.rig}
                      </span>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

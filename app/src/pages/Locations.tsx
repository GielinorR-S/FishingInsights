import { useState, useEffect, useMemo, useRef } from 'react'
import { Link } from 'react-router-dom'
import { getLocations, getHealth, type LocationsResponse, extractLocations, type Location, type ApiError } from '../services/api'
import { useFavourites } from '../contexts/FavouritesContext'
import { usePreferences } from '../contexts/PreferencesContext'
import { StarIcon, ChevronRightIcon, SearchIcon, XIcon, MapPinIcon } from '../components/icons'

type SortOption = 'nearest' | 'name' | 'region'

interface LocationWithDistance extends Location {
  distance?: number // Distance in km
  type?: string
  access?: string
}

/**
 * Calculate distance between two points using Haversine formula
 * Returns distance in kilometers
 */
function haversineDistance(lat1: number, lng1: number, lat2: number, lng2: number): number {
  const R = 6371 // Earth's radius in kilometers
  const dLat = (lat2 - lat1) * Math.PI / 180
  const dLng = (lng2 - lng1) * Math.PI / 180
  const a = 
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
    Math.sin(dLng / 2) * Math.sin(dLng / 2)
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
  return R * c
}

/**
 * Generate a unique key for a location (used for favorites)
 */
function getLocationKey(location: Location): string {
  return `${location.lat.toFixed(4)}:${location.lng.toFixed(4)}`
}

/**
 * Check if we're in development mode (localhost)
 */
function isDevMode(): boolean {
  return window.location.hostname === 'localhost' || 
         window.location.hostname === '127.0.0.1' ||
         window.location.hostname === ''
}

export default function Locations() {
  const { isFavourite, toggleFavourite } = useFavourites()
  const { lastSort, setLastSort, setLastLocation } = usePreferences()
  const [locations, setLocations] = useState<LocationWithDistance[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [apiError, setApiError] = useState<ApiError | null>(null)
  const [apiReachable, setApiReachable] = useState<boolean | null>(null)
  const [searchTerm, setSearchTerm] = useState('')
  const [sortBy, setSortBy] = useState<SortOption>(lastSort || 'name')
  const [userLocation, setUserLocation] = useState<{ lat: number; lng: number } | null>(null)
  const [locationPermission, setLocationPermission] = useState<'prompt' | 'granted' | 'denied'>('prompt')
  const [showLocationBanner, setShowLocationBanner] = useState(true)
  
  // Save sort preference when it changes
  useEffect(() => {
    if (sortBy) {
      setLastSort(sortBy)
    }
  }, [sortBy, setLastSort])
  
  // Debounced search term
  const [debouncedSearchTerm, setDebouncedSearchTerm] = useState(searchTerm)
  const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  
  useEffect(() => {
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current)
    }
    
    debounceTimerRef.current = setTimeout(() => {
      setDebouncedSearchTerm(searchTerm)
    }, 300) // 300ms debounce
    
    return () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current)
      }
    }
  }, [searchTerm])

  // Request geolocation permission
  const requestLocation = () => {
    if (!navigator.geolocation) {
      setLocationPermission('denied')
      setShowLocationBanner(false)
      return
    }

    navigator.geolocation.getCurrentPosition(
      (position) => {
        setUserLocation({
          lat: position.coords.latitude,
          lng: position.coords.longitude
        })
        setLocationPermission('granted')
        setShowLocationBanner(false)
        setSortBy('nearest')
      },
      (err) => {
        setLocationPermission('denied')
        setShowLocationBanner(false)
        console.log('Geolocation error:', err)
      },
      {
        enableHighAccuracy: false,
        timeout: 5000,
        maximumAge: 300000 // 5 minutes
      }
    )
  }

  // Check API reachability before loading locations
  useEffect(() => {
    const checkApiReachability = async () => {
      try {
        await getHealth()
        setApiReachable(true)
        // If API is reachable, load locations
        loadLocations()
      } catch (err) {
        setApiReachable(false)
        setError('API server not reachable. Start: php -S 127.0.0.1:8001 -t .')
        if (err && typeof err === 'object' && 'isNetworkError' in err) {
          setApiError(err as ApiError)
        }
        setLoading(false)
      }
    }
    
    checkApiReachability()
  }, [])

  // Calculate distances when user location is available
  useEffect(() => {
    if (userLocation && locations.length > 0) {
      const locationsWithDistance = locations.map(loc => ({
        ...loc,
        distance: haversineDistance(userLocation.lat, userLocation.lng, loc.lat, loc.lng)
      }))
      setLocations(locationsWithDistance)
    }
  }, [userLocation])

  const loadLocations = async () => {
    try {
      setLoading(true)
      setError(null)
      setApiError(null)
      const response: LocationsResponse = await getLocations()
      if (response.error) {
        setError('Failed to load locations')
      } else {
        const extracted = extractLocations(response)
        setLocations(extracted)
        setApiReachable(true)
      }
    } catch (err) {
      const apiErr = err && typeof err === 'object' && 'isNetworkError' in err 
        ? err as ApiError 
        : null
      
      setApiError(apiErr)
      
      if (apiErr?.isNetworkError) {
        setError('Unable to connect to the API server. Please check your connection and ensure the server is running.')
      } else {
        setError('Failed to load locations. Please check your connection and try again.')
      }
      console.error('Error loading locations:', err)
    } finally {
      setLoading(false)
    }
  }

  // Memoize filtered and sorted locations
  const filteredAndSortedLocations = useMemo(() => {
    let filtered = locations.filter(loc => {
      if (!debouncedSearchTerm) return true
      const search = debouncedSearchTerm.toLowerCase()
      return loc.name.toLowerCase().includes(search) || 
             (loc.region && loc.region.toLowerCase().includes(search)) ||
             (loc.type && loc.type.toLowerCase().includes(search))
    })

    // Sort
    filtered = [...filtered].sort((a, b) => {
      switch (sortBy) {
        case 'nearest':
          if (a.distance !== undefined && b.distance !== undefined) {
            return a.distance - b.distance
          }
          if (a.distance !== undefined) return -1
          if (b.distance !== undefined) return 1
          return a.name.localeCompare(b.name)
        case 'name':
          return a.name.localeCompare(b.name)
        case 'region':
          const regionA = a.region || ''
          const regionB = b.region || ''
          if (regionA === regionB) {
            return a.name.localeCompare(b.name)
          }
          return regionA.localeCompare(regionB)
        default:
          return 0
      }
    })

    return filtered
  }, [locations, debouncedSearchTerm, sortBy, userLocation])

  return (
    <div className="container">
      <header className="page-header">
        <h1 className="page-title">Select Location</h1>
        <p className="page-subtitle">Choose a fishing location to view forecasts</p>
      </header>

      <div className="space-y-5">
        {/* Location Permission Banner */}
        {showLocationBanner && locationPermission === 'prompt' && (
          <div className="card bg-gradient-to-r from-blue-50 to-indigo-50 border-blue-200">
            <div className="flex items-start justify-between gap-3">
              <div className="flex-1">
                <p className="font-semibold text-sm mb-1">Enable location to find nearest spots</p>
                <p className="text-xs">
                  We'll sort locations by distance from you. Your location is not stored.
                </p>
              </div>
              <div className="flex gap-2 flex-shrink-0">
                <button
                  onClick={() => {
                    setShowLocationBanner(false)
                    setLocationPermission('denied')
                  }}
                  className="btn-ghost text-xs px-2 py-1"
                >
                  Dismiss
                </button>
                <button
                  onClick={requestLocation}
                  className="btn-primary text-xs px-3 py-1"
                >
                  Enable
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Search and Sort */}
        <div className="card space-y-4">
          <div className="relative">
            <input
              type="text"
              placeholder="Search locations..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full px-4 py-3.5 bg-white/90 backdrop-blur-sm border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all shadow-sm font-medium"
            />
            {searchTerm && (
              <button
                onClick={() => setSearchTerm('')}
                className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                aria-label="Clear search"
              >
                <XIcon className="w-5 h-5" />
              </button>
            )}
          </div>

          <div className="flex items-center gap-2">
            <label htmlFor="sort-select" className="text-sm font-medium text-gray-700 whitespace-nowrap">
              Sort by:
            </label>
            <select
              id="sort-select"
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value as SortOption)}
              className="flex-1 px-3 py-2.5 bg-white/80 backdrop-blur-sm border-2 border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all shadow-sm"
            >
              <option value="nearest" disabled={!userLocation}>
                {userLocation ? 'Nearest First' : 'Nearest (enable location)'}
              </option>
              <option value="name">Name (A-Z)</option>
              <option value="region">Region</option>
            </select>
          </div>
        </div>

        {/* Loading State */}
        {loading && (
          <div className="text-center py-8">
            <div className="spinner mx-auto mb-3"></div>
            <p className="text-gray-600 text-sm">Loading locations...</p>
          </div>
        )}

        {/* Error State */}
        {error && (
          <div className="banner banner-error">
            <p className="font-semibold mb-2">Unable to load locations</p>
            <p className="text-sm mb-3">{error}</p>
            <div className="space-y-2">
              <p className="text-xs font-medium text-gray-700">What to do next:</p>
              <ul className="text-xs text-gray-600 space-y-1 list-disc list-inside">
                <li>Check your internet connection</li>
                <li>If you're a developer, ensure the API server is running</li>
                <li>Try clicking Retry below</li>
                {locations.length === 0 && apiReachable !== false && (
                  <li>If you're in development mode, you may need to seed the database</li>
                )}
              </ul>
              <div className="flex gap-2 mt-3">
                <button onClick={loadLocations} className="btn-primary">
                  Retry
                </button>
                {locations.length === 0 && apiReachable === true && (
                  <a
                    href="/api/seed.php"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="btn-secondary"
                  >
                    Run Seed (DEV)
                  </a>
                )}
                {locations.length === 0 && apiReachable === false && (
                  <a
                    href="/api/seed.php"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="btn-secondary"
                    title="Starts seeding via API (server must be running)"
                  >
                    Run Seed (DEV)
                  </a>
                )}
              </div>
            </div>
            
            {/* DEV-only diagnostics */}
            {isDevMode() && apiError && (
              <div className="mt-4 pt-4 border-t border-gray-300 bg-gray-50 rounded-lg p-3">
                <p className="text-xs font-semibold text-gray-700 mb-2">DEV Diagnostics:</p>
                <div className="space-y-1 text-xs font-mono text-gray-600">
                  <div>
                    <span className="font-semibold">URL:</span> {apiError.url}
                    {!apiError.url.startsWith('http') && (
                      <span className="text-gray-500 ml-2">(relative, proxied by Vite)</span>
                    )}
                  </div>
                  {apiError.status !== undefined && (
                    <div>
                      <span className="font-semibold">Status:</span> {apiError.status} {apiError.statusText || ''}
                    </div>
                  )}
                  <div>
                    <span className="font-semibold">Error:</span> {apiError.message}
                  </div>
                  <div>
                    <span className="font-semibold">Network Error:</span> {apiError.isNetworkError ? 'Yes' : 'No'}
                  </div>
                </div>
              </div>
            )}
          </div>
        )}

        {/* Empty State */}
        {!loading && !error && filteredAndSortedLocations.length === 0 && locations.length === 0 && (
          <div className="card text-center py-8">
            <div className="relative mx-auto mb-4 w-20 h-20 rounded-full overflow-hidden bg-gray-100">
              <img 
                src="/assets/empty-state-fishing.jpg" 
                alt="Fishing"
                className="w-full h-full object-cover opacity-60"
                onError={(e) => {
                  e.currentTarget.style.display = 'none'
                }}
              />
              <div className="absolute inset-0 flex items-center justify-center">
                <MapPinIcon className="w-10 h-10 text-gray-400" size={40} />
              </div>
            </div>
            <h3 className="text-lg font-semibold text-gray-900 mb-2">No fishing locations available</h3>
            <p className="text-sm text-gray-600 mb-4">
              We're setting up the location database. This usually happens automatically.
            </p>
            <div className="space-y-2">
              <p className="text-xs font-medium text-gray-700">What to do next:</p>
              <ul className="text-xs text-gray-600 space-y-1 list-disc list-inside max-w-sm mx-auto">
                <li>Wait a moment and refresh the page</li>
                <li>Check your internet connection</li>
                <li>If you're a developer, you may need to seed the database</li>
              </ul>
            </div>
            <a
              href="/api/seed.php"
              target="_blank"
              rel="noopener noreferrer"
              className="btn-primary inline-block mt-4"
            >
              Run Seed (DEV)
            </a>
          </div>
        )}

        {/* No Search Results */}
        {!loading && !error && filteredAndSortedLocations.length === 0 && locations.length > 0 && (
          <div className="card text-center py-8">
            <SearchIcon className="w-12 h-12 mx-auto mb-3 text-gray-300" size={48} />
            <h3 className="text-base font-semibold text-gray-900 mb-2">No locations found</h3>
            <p className="text-sm text-gray-600 mb-4">
              We couldn't find any locations matching "{searchTerm}".
            </p>
            <div className="space-y-2">
              <p className="text-xs font-medium text-gray-700">Try:</p>
              <ul className="text-xs text-gray-600 space-y-1 list-disc list-inside max-w-sm mx-auto">
                <li>Checking your spelling</li>
                <li>Searching by region or location type</li>
                <li>Clearing your search to see all locations</li>
              </ul>
            </div>
            <button
              onClick={() => setSearchTerm('')}
              className="btn-secondary mt-4"
            >
              Clear Search
            </button>
          </div>
        )}

        {/* Location Cards */}
        {!loading && !error && filteredAndSortedLocations.length > 0 && (
          <div className="grid grid-cols-1 gap-3">
            {filteredAndSortedLocations.map((location) => {
              const locationKey = getLocationKey(location)
              const isStarred = isFavourite(locationKey)
              
              return (
                <div
                  key={location.id}
                  className="card hover:shadow-md transition-all duration-200 hover:border-primary-300 border-l-4 border-l-primary-500"
                >
                  <div className="flex justify-between items-start gap-3">
                    <Link
                      to={`/forecast?lat=${location.lat}&lng=${location.lng}`}
                      onClick={() => {
                        // Save location when navigating to forecast
                        setLastLocation(location.lat, location.lng)
                      }}
                      className="flex-1 min-w-0"
                    >
                      <div className="flex items-start justify-between gap-2 mb-1">
                        <h3 className="font-semibold text-base text-gray-900 truncate">{location.name}</h3>
                        {location.distance !== undefined && (
                          <span className="badge bg-primary-100 text-primary-900 flex-shrink-0">
                            {location.distance.toFixed(1)} km
                          </span>
                        )}
                      </div>
                      
                      <div className="flex flex-wrap items-center gap-2 mt-2">
                        {location.region && (
                          <span className="badge bg-blue-50 text-blue-700 border border-blue-200">
                            {location.region}
                          </span>
                        )}
                        {location.type && (
                          <span className="badge bg-gray-100 text-gray-700 border border-gray-200 capitalize">
                            {location.type}
                          </span>
                        )}
                        {location.access && (
                          <span className="badge bg-green-50 text-green-700 border border-green-200 text-xs">
                            {location.access}
                          </span>
                        )}
                      </div>
                    </Link>
                    
                    <div className="flex items-start gap-2 flex-shrink-0">
                      <button
                        onClick={(e) => {
                          e.preventDefault()
                          e.stopPropagation()
                          toggleFavourite(locationKey)
                        }}
                        className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
                        aria-label={isStarred ? 'Remove from favourites' : 'Add to favourites'}
                      >
                        <StarIcon
                          className={`w-5 h-5 transition-colors ${
                            isStarred
                              ? 'fill-yellow-400 text-yellow-400'
                              : 'text-gray-400 hover:text-yellow-400'
                          }`}
                          filled={isStarred}
                        />
                      </button>
                      
                      <Link
                        to={`/forecast?lat=${location.lat}&lng=${location.lng}`}
                        className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
                        aria-label="View forecast"
                      >
                        <ChevronRightIcon className="w-5 h-5 text-gray-400" />
                      </Link>
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </div>
    </div>
  )
}

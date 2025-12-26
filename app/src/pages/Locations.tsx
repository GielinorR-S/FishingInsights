import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { getLocations, type LocationsResponse, extractLocations, type Location, getApiBasePathForLinks } from '../services/api'

export default function Locations() {
  const [locations, setLocations] = useState<Location[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [searchTerm, setSearchTerm] = useState('')
  useEffect(() => {
    loadLocations()
  }, [])

  const loadLocations = async () => {
    try {
      setLoading(true)
      setError(null)
      const response: LocationsResponse = await getLocations()
      if (response.error) {
        setError('Failed to load locations')
      } else {
        setLocations(extractLocations(response))
      }
    } catch (err) {
      setError('Failed to load locations. Make sure the API server is running.')
      console.error('Error loading locations:', err)
    } finally {
      setLoading(false)
    }
  }

  const filteredLocations = locations.filter(loc => {
    if (!searchTerm) return true
    const search = searchTerm.toLowerCase()
    return loc.name.toLowerCase().includes(search) || 
           (loc.region && loc.region.toLowerCase().includes(search))
  })

  return (
    <div className="container mx-auto px-4 py-6 pb-20">
      <header className="page-header">
        <h1 className="page-title">Select Location</h1>
        <p className="page-subtitle">Choose a fishing location to view forecasts</p>
      </header>

      <div className="space-y-4">
        <div className="relative">
          <input
            type="text"
            placeholder="Search locations..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
          {searchTerm && (
            <button
              onClick={() => setSearchTerm('')}
              className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          )}
        </div>

        {loading && (
          <div className="text-center py-8">
            <div className="spinner mx-auto mb-3"></div>
            <p className="text-gray-600 text-sm">Loading locations...</p>
          </div>
        )}

        {error && (
          <div className="banner banner-error">
            <p className="font-semibold mb-2">{error}</p>
            {locations.length === 0 && (
              <a
                href={`${getApiBasePathForLinks()}/seed.php`}
                target="_blank"
                rel="noopener noreferrer"
                className="btn-primary inline-block mt-2"
              >
                Run Seed (DEV)
              </a>
            )}
          </div>
        )}

        {!loading && !error && filteredLocations.length === 0 && locations.length === 0 && (
          <div className="banner banner-warning text-center">
            <p className="font-semibold mb-2">No locations found</p>
            <p className="text-sm mb-3 opacity-90">
              The database appears to be empty. Seed the database with initial data.
            </p>
            <a
              href={`${getApiBasePathForLinks()}/seed.php`}
              target="_blank"
              rel="noopener noreferrer"
              className="btn-primary inline-block"
            >
              Run Seed (DEV)
            </a>
          </div>
        )}

        {!loading && !error && filteredLocations.length === 0 && locations.length > 0 && (
          <p className="text-gray-600 text-center py-4">
            No locations match "{searchTerm}"
          </p>
        )}

        {!loading && !error && filteredLocations.length > 0 && (
          <div className="grid grid-cols-1 gap-3">
            {filteredLocations.map((location) => (
              <Link
                key={location.id}
                to={`/forecast?lat=${location.lat}&lng=${location.lng}`}
                className="card hover:shadow-md transition-all duration-200 hover:border-primary-300"
              >
                <div className="flex justify-between items-center">
                  <div className="flex-1">
                    <h3 className="font-semibold text-base text-gray-900 mb-0.5">{location.name}</h3>
                    {location.region && (
                      <p className="text-sm text-gray-500">{location.region}</p>
                    )}
                  </div>
                  <svg
                    className="w-5 h-5 text-gray-400 flex-shrink-0 ml-3"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M9 5l7 7-7 7"
                    />
                  </svg>
                </div>
              </Link>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}


import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { getLocations, type LocationsResponse } from '../services/api'

interface Location {
  id: number
  name: string
  region: string
  lat: number
  lng: number
  timezone: string
}

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
        setLocations(response.data.locations)
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
           loc.region.toLowerCase().includes(search)
  })

  return (
    <div className="container mx-auto px-4 py-6">
      <header className="mb-6">
        <h1 className="text-2xl font-bold">Select Location</h1>
      </header>

      <div className="space-y-4">
        <input
          type="text"
          placeholder="Search locations..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className="w-full px-4 py-2 border border-gray-300 rounded-lg"
        />

        {loading && (
          <p className="text-gray-600 text-center py-4">Loading locations...</p>
        )}

        {error && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-4">
            <p className="text-red-800 font-medium mb-2">{error}</p>
            {locations.length === 0 && (
              <a
                href="/api/seed.php"
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
          <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <p className="text-yellow-800 font-medium mb-2">No locations found</p>
            <p className="text-yellow-700 text-sm mb-3">
              The database appears to be empty. Seed the database with initial data.
            </p>
            <a
              href="/api/seed.php"
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
                to={`/forecast/${location.id}?lat=${location.lat}&lng=${location.lng}`}
                className="card hover:shadow-lg transition-shadow"
              >
                <div className="flex justify-between items-start">
                  <div>
                    <h3 className="font-semibold text-lg">{location.name}</h3>
                    <p className="text-gray-600 text-sm">{location.region}</p>
                  </div>
                  <svg
                    className="w-5 h-5 text-gray-400"
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


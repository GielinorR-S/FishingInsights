import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useFavourites } from '../contexts/FavouritesContext'
import { useTargetSpecies } from '../contexts/TargetSpeciesContext'
import { getLocations, extractLocations, getTodaysBest, type Location, type TodaysBestLocation } from '../services/api'
import { StarIcon, ChevronRightIcon, MapPinIcon } from '../components/icons'

/**
 * Generate a unique key for a location (used for favorites)
 */
function getLocationKey(location: Location): string {
  return `${location.lat.toFixed(4)}:${location.lng.toFixed(4)}`
}

export default function Home() {
  const { favourites } = useFavourites()
  const { targetSpecies } = useTargetSpecies()
  const [favouriteLocations, setFavouriteLocations] = useState<Location[]>([])
  const [todaysBest, setTodaysBest] = useState<TodaysBestLocation[]>([])
  const [loadingBest, setLoadingBest] = useState(true)

  useEffect(() => {
    const loadFavourites = async () => {
      if (favourites.length === 0) {
        setFavouriteLocations([])
        return
      }

      try {
        const response = await getLocations()
        const allLocations = extractLocations(response)
        
        // Filter to only favourite locations
        const favs = allLocations.filter(loc => {
          const key = getLocationKey(loc)
          return favourites.includes(key)
        })
        
        setFavouriteLocations(favs)
      } catch (err) {
        console.error('Failed to load favourite locations:', err)
        setFavouriteLocations([])
      }
    }

    loadFavourites()
  }, [favourites])

  useEffect(() => {
    const loadTodaysBest = async () => {
      setLoadingBest(true)
      try {
        // Use first target species if available, otherwise general
        const speciesId = targetSpecies.length > 0 ? targetSpecies[0] : undefined
        const response = await getTodaysBest('VIC', undefined, 5, speciesId)
        if (!response.error && response.data.locations) {
          setTodaysBest(response.data.locations)
        }
      } catch (err) {
        console.error('Failed to load today\'s best:', err)
        setTodaysBest([])
      } finally {
        setLoadingBest(false)
      }
    }

    loadTodaysBest()
  }, [targetSpecies])

  return (
    <div className="container">
      {/* Hero Section */}
      <header className="page-header mb-8">
        <h1 className="page-title">FishingInsights</h1>
        <p className="page-subtitle">Fishing forecasts for Victorian anglers</p>
      </header>

      <div className="space-y-6">
        {/* Browse Locations CTA - Top Priority */}
        <Link 
          to="/locations" 
          className="card block no-underline hover:no-underline group"
        >
          <div className="flex items-center justify-center gap-4 py-2">
            <div className="p-3 bg-gradient-to-br from-primary-500 to-primary-600 rounded-xl shadow-lg group-hover:shadow-xl transition-shadow">
              <MapPinIcon className="w-7 h-7 text-white" size={28} />
            </div>
            <div className="flex-1">
              <h2 className="text-xl font-bold text-gray-900 mb-1">Browse Locations</h2>
              <p className="text-sm text-gray-600">Explore fishing spots across Victoria</p>
            </div>
            <ChevronRightIcon className="w-6 h-6 text-gray-400 group-hover:text-primary-600 transition-colors" size={24} />
          </div>
        </Link>

        {/* Favourites Section */}
        <div className="card">
          <div className="flex items-center justify-between mb-5">
            <h2 className="section-heading mb-0">Favourites</h2>
            {favouriteLocations.length > 0 && (
              <span className="badge bg-gradient-to-r from-primary-500 to-primary-600 text-white border-0 shadow-sm font-semibold">
                {favouriteLocations.length}
              </span>
            )}
          </div>
          
          {favouriteLocations.length === 0 ? (
            <div className="text-center py-10">
              <div className="relative mx-auto mb-6 w-24 h-24 rounded-full bg-gradient-to-br from-blue-100 to-cyan-100 flex items-center justify-center shadow-inner">
                <StarIcon className="w-12 h-12 text-blue-400" size={48} />
              </div>
              <p className="text-gray-700 text-lg mb-2 font-bold">No favourites yet</p>
              <p className="text-sm text-gray-600 mb-6 leading-relaxed max-w-sm mx-auto">
                Star your favorite fishing spots to access them quickly from here.
              </p>
              <Link to="/locations" className="btn-primary inline-flex items-center gap-2">
                Browse locations
                <ChevronRightIcon className="w-4 h-4" size={16} />
              </Link>
            </div>
          ) : (
            <div className="space-y-3">
              {favouriteLocations.map((location) => (
                <Link
                  key={location.id}
                  to={`/forecast?lat=${location.lat}&lng=${location.lng}`}
                  className="block p-5 bg-gradient-to-r from-gray-50 via-blue-50 to-cyan-50 rounded-xl border-2 border-gray-200 hover:border-primary-400 hover:shadow-lg transition-all duration-300 group"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex-1 min-w-0">
                      <h3 className="font-bold text-lg text-gray-900 truncate mb-1.5 group-hover:text-primary-700 transition-colors">{location.name}</h3>
                      {location.region && (
                        <p className="text-sm text-gray-600 font-semibold">{location.region}</p>
                      )}
                    </div>
                    <ChevronRightIcon className="w-6 h-6 text-gray-400 group-hover:text-primary-600 group-hover:translate-x-1 transition-all flex-shrink-0 ml-3" size={24} />
                  </div>
                </Link>
              ))}
            </div>
          )}
        </div>

        {/* Today's Best Section */}
        <div className="card">
          <div className="flex items-center justify-between mb-5">
            <h2 className="section-heading mb-0">
              Today's Best
            </h2>
            {targetSpecies.length > 0 && (
              <span className="text-xs font-medium text-gray-500 normal-case bg-gray-100 px-2 py-1 rounded-full">
                {targetSpecies.length} target species
              </span>
            )}
          </div>
          
          {loadingBest ? (
            <div className="text-center py-8">
              <div className="inline-block w-8 h-8 border-3 border-primary-600 border-t-transparent rounded-full animate-spin mb-3"></div>
              <p className="text-gray-600 text-sm font-medium">Loading top spots...</p>
            </div>
          ) : todaysBest.length === 0 ? (
            <div className="text-center py-8">
              <p className="text-gray-700 text-base mb-2 font-semibold">No locations available</p>
              <p className="text-sm text-gray-600">
                Check back later for today's top fishing spots.
              </p>
            </div>
          ) : (
            <div className="space-y-4">
              {todaysBest.map((location) => (
                <Link
                  key={location.id}
                  to={`/forecast?lat=${location.lat}&lng=${location.lng}`}
                  className="block p-5 bg-gradient-to-br from-blue-50 via-cyan-50 to-teal-50 rounded-xl border-2 border-blue-200 hover:border-primary-400 hover:shadow-lg transition-all duration-300 group"
                >
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-3 mb-3">
                        <h3 className="font-bold text-lg text-gray-900 truncate group-hover:text-primary-700 transition-colors">
                          {location.name}
                        </h3>
                        <span className={`badge flex-shrink-0 ${
                          location.score >= 70 
                            ? 'badge-quality-excellent' 
                            : location.score >= 50 
                            ? 'badge-quality-good' 
                            : 'badge-quality-fair'
                        }`}>
                          {location.score}
                        </span>
                      </div>
                      {location.region && (
                        <p className="text-sm text-gray-600 mb-2.5 font-semibold">{location.region}</p>
                      )}
                      <p className="text-sm text-gray-700 leading-relaxed font-medium">
                        {location.why}
                      </p>
                    </div>
                    <ChevronRightIcon className="w-6 h-6 text-gray-400 group-hover:text-primary-600 group-hover:translate-x-1 transition-all flex-shrink-0 mt-1" size={24} />
                  </div>
                </Link>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

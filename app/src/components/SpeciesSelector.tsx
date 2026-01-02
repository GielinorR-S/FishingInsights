import { useState, useEffect, useMemo } from 'react'
import { getSpecies, type Species } from '../services/api'
import { useTargetSpecies } from '../contexts/TargetSpeciesContext'
import { SearchIcon, XIcon } from './icons'

interface SpeciesSelectorProps {
  locationRegion?: string | null
  locationState?: string
  onSelectionChange?: (selectedIds: string[]) => void
  className?: string
}

export default function SpeciesSelector({
  locationRegion,
  locationState = 'VIC',
  onSelectionChange,
  className = ''
}: SpeciesSelectorProps) {
  const { targetSpecies, toggleTargetSpecies, hasTargetSpecies } = useTargetSpecies()
  const [species, setSpecies] = useState<Species[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [searchTerm, setSearchTerm] = useState('')

  // Load species filtered by location region/state
  useEffect(() => {
    const loadSpecies = async () => {
      setLoading(true)
      setError(null)
      try {
        const response = await getSpecies(locationState, locationRegion || undefined)
        if (response.error) {
          throw new Error('Failed to load species')
        }
        setSpecies(response.data.species)
      } catch (err) {
        console.error('Failed to load species:', err)
        setError('Failed to load species. Please try again.')
      } finally {
        setLoading(false)
      }
    }

    loadSpecies()
  }, [locationState, locationRegion])

  // Filter species by search term
  const filteredSpecies = useMemo(() => {
    if (!searchTerm.trim()) {
      return species
    }
    const term = searchTerm.toLowerCase()
    return species.filter(
      s =>
        s.name.toLowerCase().includes(term) ||
        s.common_name.toLowerCase().includes(term) ||
        (s.notes && s.notes.toLowerCase().includes(term))
    )
  }, [species, searchTerm])

  // Group species by common name (deduplicate)
  const uniqueSpecies = useMemo(() => {
    const seen = new Map<string, Species>()
    filteredSpecies.forEach(s => {
      const key = s.common_name.toLowerCase()
      if (!seen.has(key)) {
        seen.set(key, s)
      }
    })
    return Array.from(seen.values()).sort((a, b) =>
      a.common_name.localeCompare(b.common_name)
    )
  }, [filteredSpecies])

  // Notify parent of selection changes
  useEffect(() => {
    if (onSelectionChange) {
      onSelectionChange(targetSpecies)
    }
  }, [targetSpecies, onSelectionChange])

  const handleToggle = (speciesId: string) => {
    toggleTargetSpecies(speciesId)
  }

  if (loading) {
    return (
      <div className={`card ${className}`}>
        <div className="text-center py-4">
          <div className="spinner mx-auto mb-3"></div>
          <p className="text-gray-600 text-sm">Loading species...</p>
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div className={`card ${className}`}>
        <div className="banner banner-error">
          <p className="font-semibold mb-2">{error}</p>
        </div>
      </div>
    )
  }

  return (
    <div className={`card ${className}`}>
      <div className="mb-4">
        <h3 className="section-heading mb-3">Target Species</h3>
        <p className="text-sm text-gray-600 mb-3">
          Select the species you're targeting. The forecast will prioritize these species.
        </p>

        {/* Search */}
        <div className="relative mb-3">
          <SearchIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            type="text"
            placeholder="Search species..."
            value={searchTerm}
            onChange={e => setSearchTerm(e.target.value)}
            className="input-text pl-10 pr-10 w-full"
          />
          {searchTerm && (
            <button
              onClick={() => setSearchTerm('')}
              className="absolute right-3 top-1/2 transform -translate-y-1/2 p-1 rounded hover:bg-gray-100"
              aria-label="Clear search"
            >
              <XIcon className="w-4 h-4 text-gray-400" />
            </button>
          )}
        </div>
      </div>

      {/* Species List */}
      {uniqueSpecies.length === 0 ? (
        <div className="text-center py-4">
          <p className="text-gray-600 text-sm">
            {searchTerm ? 'No species found matching your search.' : 'No species available for this location.'}
          </p>
        </div>
      ) : (
        <div className="space-y-2 max-h-64 overflow-y-auto">
          {uniqueSpecies.map(s => {
            const isSelected = hasTargetSpecies(s.name)
            return (
              <button
                key={s.id}
                onClick={() => handleToggle(s.name)}
                className={`w-full text-left p-3 rounded-lg border transition-colors ${
                  isSelected
                    ? 'bg-primary-50 border-primary-300 text-primary-900'
                    : 'bg-gray-50 border-gray-200 text-gray-900 hover:bg-gray-100'
                }`}
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="flex-1 min-w-0">
                    <div className="font-semibold text-sm mb-1">{s.common_name}</div>
                    {s.region && (
                      <div className="text-xs text-gray-600 mb-1">{s.region}</div>
                    )}
                    {s.seasonality && (
                      <div className="text-xs text-gray-500">{s.seasonality}</div>
                    )}
                  </div>
                  <div
                    className={`flex-shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center ${
                      isSelected
                        ? 'bg-primary-600 border-primary-600'
                        : 'border-gray-300'
                    }`}
                  >
                    {isSelected && (
                      <svg
                        className="w-3 h-3 text-white"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={3}
                          d="M5 13l4 4L19 7"
                        />
                      </svg>
                    )}
                  </div>
                </div>
              </button>
            )
          })}
        </div>
      )}

      {/* Selected Count */}
      {targetSpecies.length > 0 && (
        <div className="mt-4 pt-4 border-t border-gray-200">
          <p className="text-sm text-gray-600">
            <span className="font-semibold">{targetSpecies.length}</span> species selected
          </p>
        </div>
      )}
    </div>
  )
}


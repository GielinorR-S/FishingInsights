import React, { createContext, useContext, useState, useEffect, useCallback } from 'react'

interface TargetSpeciesContextType {
  targetSpecies: string[] // Array of species IDs (e.g., ['snapper', 'whiting'])
  setTargetSpecies: (species: string[]) => void
  addTargetSpecies: (speciesId: string) => void
  removeTargetSpecies: (speciesId: string) => void
  toggleTargetSpecies: (speciesId: string) => void
  hasTargetSpecies: (speciesId: string) => boolean
  clearTargetSpecies: () => void
}

const TargetSpeciesContext = createContext<TargetSpeciesContextType | undefined>(undefined)

const LOCAL_STORAGE_KEY = 'fishinginsights_target_species'

export function TargetSpeciesProvider({ children }: { children: React.ReactNode }) {
  const [targetSpecies, setTargetSpeciesState] = useState<string[]>([])

  // Load from localStorage on mount
  useEffect(() => {
    try {
      const stored = localStorage.getItem(LOCAL_STORAGE_KEY)
      if (stored) {
        const species = JSON.parse(stored)
        if (Array.isArray(species)) {
          setTargetSpeciesState(species)
        }
      }
    } catch (e) {
      console.error('Failed to parse target species from localStorage', e)
    }
  }, [])

  // Save to localStorage whenever targetSpecies changes
  useEffect(() => {
    try {
      localStorage.setItem(LOCAL_STORAGE_KEY, JSON.stringify(targetSpecies))
    } catch (e) {
      console.error('Failed to save target species to localStorage', e)
    }
  }, [targetSpecies])

  const setTargetSpecies = useCallback((species: string[]) => {
    setTargetSpeciesState(species)
  }, [])

  const addTargetSpecies = useCallback((speciesId: string) => {
    setTargetSpeciesState(prev => {
      if (prev.includes(speciesId)) {
        return prev
      }
      return [...prev, speciesId]
    })
  }, [])

  const removeTargetSpecies = useCallback((speciesId: string) => {
    setTargetSpeciesState(prev => prev.filter(id => id !== speciesId))
  }, [])

  const toggleTargetSpecies = useCallback((speciesId: string) => {
    setTargetSpeciesState(prev => {
      if (prev.includes(speciesId)) {
        return prev.filter(id => id !== speciesId)
      }
      return [...prev, speciesId]
    })
  }, [])

  const hasTargetSpecies = useCallback((speciesId: string) => {
    return targetSpecies.includes(speciesId)
  }, [targetSpecies])

  const clearTargetSpecies = useCallback(() => {
    setTargetSpeciesState([])
    try {
      localStorage.removeItem(LOCAL_STORAGE_KEY)
    } catch (e) {
      console.error('Failed to clear target species from localStorage', e)
    }
  }, [])

  return (
    <TargetSpeciesContext.Provider
      value={{
        targetSpecies,
        setTargetSpecies,
        addTargetSpecies,
        removeTargetSpecies,
        toggleTargetSpecies,
        hasTargetSpecies,
        clearTargetSpecies
      }}
    >
      {children}
    </TargetSpeciesContext.Provider>
  )
}

export function useTargetSpecies() {
  const context = useContext(TargetSpeciesContext)
  if (context === undefined) {
    throw new Error('useTargetSpecies must be used within a TargetSpeciesProvider')
  }
  return context
}


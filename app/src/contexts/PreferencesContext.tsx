import React, { createContext, useContext, useState, useEffect } from 'react'

interface PreferencesContextType {
  lastLocation: { lat: number; lng: number } | null
  lastDate: string | null
  lastSort: 'nearest' | 'name' | 'region' | null
  setLastLocation: (lat: number, lng: number) => void
  setLastDate: (date: string) => void
  setLastSort: (sort: 'nearest' | 'name' | 'region') => void
  resetAll: () => void
}

const PreferencesContext = createContext<PreferencesContextType | undefined>(undefined)

const STORAGE_KEY = 'fishinginsights_preferences'

interface StoredPreferences {
  lastLocation?: { lat: number; lng: number }
  lastDate?: string
  lastSort?: 'nearest' | 'name' | 'region'
}

export function PreferencesProvider({ children }: { children: React.ReactNode }) {
  const [lastLocation, setLastLocationState] = useState<{ lat: number; lng: number } | null>(null)
  const [lastDate, setLastDateState] = useState<string | null>(null)
  const [lastSort, setLastSortState] = useState<'nearest' | 'name' | 'region' | null>(null)

  // Load from localStorage on mount
  useEffect(() => {
    try {
      const stored = localStorage.getItem(STORAGE_KEY)
      if (stored) {
        const prefs: StoredPreferences = JSON.parse(stored)
        if (prefs.lastLocation) {
          setLastLocationState(prefs.lastLocation)
        }
        if (prefs.lastDate) {
          setLastDateState(prefs.lastDate)
        }
        if (prefs.lastSort) {
          setLastSortState(prefs.lastSort)
        }
      }
    } catch (e) {
      console.error('Failed to load preferences', e)
    }
  }, [])

  // Save to localStorage whenever preferences change
  useEffect(() => {
    try {
      const prefs: StoredPreferences = {}
      if (lastLocation) {
        prefs.lastLocation = lastLocation
      }
      if (lastDate) {
        prefs.lastDate = lastDate
      }
      if (lastSort) {
        prefs.lastSort = lastSort
      }
      localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs))
    } catch (e) {
      console.error('Failed to save preferences', e)
    }
  }, [lastLocation, lastDate, lastSort])

  const setLastLocation = (lat: number, lng: number) => {
    setLastLocationState({ lat, lng })
  }

  const setLastDate = (date: string) => {
    setLastDateState(date)
  }

  const setLastSort = (sort: 'nearest' | 'name' | 'region') => {
    setLastSortState(sort)
  }

  const resetAll = () => {
    setLastLocationState(null)
    setLastDateState(null)
    setLastSortState(null)
    localStorage.removeItem(STORAGE_KEY)
  }

  return (
    <PreferencesContext.Provider
      value={{
        lastLocation,
        lastDate,
        lastSort,
        setLastLocation,
        setLastDate,
        setLastSort,
        resetAll
      }}
    >
      {children}
    </PreferencesContext.Provider>
  )
}

export function usePreferences() {
  const context = useContext(PreferencesContext)
  if (context === undefined) {
    throw new Error('usePreferences must be used within PreferencesProvider')
  }
  return context
}


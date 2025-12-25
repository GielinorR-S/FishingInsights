import React, { createContext, useContext, useState, useEffect } from 'react'

interface FavouritesContextType {
  favourites: string[]
  addFavourite: (locationId: string) => void
  removeFavourite: (locationId: string) => void
  isFavourite: (locationId: string) => boolean
  toggleFavourite: (locationId: string) => void
}

const FavouritesContext = createContext<FavouritesContextType | undefined>(undefined)

export function FavouritesProvider({ children }: { children: React.ReactNode }) {
  const [favourites, setFavourites] = useState<string[]>([])

  useEffect(() => {
    const stored = localStorage.getItem('fishinginsights_favourites')
    if (stored) {
      try {
        setFavourites(JSON.parse(stored))
      } catch (e) {
        console.error('Failed to parse favourites', e)
      }
    }
  }, [])

  useEffect(() => {
    localStorage.setItem('fishinginsights_favourites', JSON.stringify(favourites))
  }, [favourites])

  const addFavourite = (locationId: string) => {
    setFavourites(prev => {
      if (prev.includes(locationId)) return prev
      return [...prev, locationId]
    })
  }

  const removeFavourite = (locationId: string) => {
    setFavourites(prev => prev.filter(id => id !== locationId))
  }

  const isFavourite = (locationId: string) => {
    return favourites.includes(locationId)
  }

  const toggleFavourite = (locationId: string) => {
    if (isFavourite(locationId)) {
      removeFavourite(locationId)
    } else {
      addFavourite(locationId)
    }
  }

  return (
    <FavouritesContext.Provider value={{ favourites, addFavourite, removeFavourite, isFavourite, toggleFavourite }}>
      {children}
    </FavouritesContext.Provider>
  )
}

export function useFavourites() {
  const context = useContext(FavouritesContext)
  if (context === undefined) {
    throw new Error('useFavourites must be used within FavouritesProvider')
  }
  return context
}


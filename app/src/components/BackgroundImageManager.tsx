import { useState, useEffect } from 'react'

/**
 * BackgroundImageManager
 * Cycles through fishing background images every 8 seconds with smooth transitions
 */

// Image paths - these exist in app/public/assets/
const BACKGROUND_IMAGES = [
  '/assets/1.jpg',      // Fishing image 1
  '/assets/2.jpg',      // Fishing image 2
  '/assets/3.jpg'       // Fishing image 3
]

const ROTATION_INTERVAL = 8000 // 8 seconds
const FADE_DURATION = 1500 // 1.5 seconds for smooth fade

export default function BackgroundImageManager() {
  const [currentIndex, setCurrentIndex] = useState(0)
  const [nextIndex, setNextIndex] = useState(1)
  const [fadeState, setFadeState] = useState<'visible' | 'fading' | 'hidden'>('visible')

  useEffect(() => {
    // Preload all images
    BACKGROUND_IMAGES.forEach((src) => {
      const img = new Image()
      img.src = src
    })

    const interval = setInterval(() => {
      setFadeState('fading')
      
      setTimeout(() => {
        setCurrentIndex((prev) => {
          const next = (prev + 1) % BACKGROUND_IMAGES.length
          setNextIndex((next + 1) % BACKGROUND_IMAGES.length)
          return next
        })
        setFadeState('visible')
      }, FADE_DURATION)
    }, ROTATION_INTERVAL)

    return () => clearInterval(interval)
  }, [])

  const currentImage = BACKGROUND_IMAGES[currentIndex]
  const nextImage = BACKGROUND_IMAGES[nextIndex]

  return (
    <div className="background-image-manager">
      {/* Current visible image */}
      <div
        className={`background-image-layer ${fadeState === 'visible' ? 'opacity-100' : 'opacity-0'}`}
        style={{
          backgroundImage: `url(${currentImage})`,
          transition: `opacity ${FADE_DURATION}ms ease-in-out`
        }}
      />
      
      {/* Next image (preloading during fade) */}
      {fadeState === 'fading' && (
        <div
          className="background-image-layer opacity-0"
          style={{
            backgroundImage: `url(${nextImage})`,
            transition: `opacity ${FADE_DURATION}ms ease-in-out`
          }}
        />
      )}
      
      {/* Dark gradient overlay for readability */}
      <div className="background-overlay" />
    </div>
  )
}


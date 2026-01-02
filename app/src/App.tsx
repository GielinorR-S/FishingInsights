import { BrowserRouter, Routes, Route } from 'react-router-dom'
import Home from './pages/Home'
import Locations from './pages/Locations'
import Forecast from './pages/Forecast'
import References from './pages/References'
import { FavouritesProvider } from './contexts/FavouritesContext'
import { PreferencesProvider } from './contexts/PreferencesContext'
import { TargetSpeciesProvider } from './contexts/TargetSpeciesContext'
import BottomNav from './components/BottomNav'
import BackgroundImageManager from './components/BackgroundImageManager'

function App() {
  return (
    <FavouritesProvider>
      <PreferencesProvider>
        <TargetSpeciesProvider>
          <BrowserRouter>
            <div className="app-wrapper">
              <BackgroundImageManager />
              <div className="app-content min-h-screen pb-20 safe-area-bottom">
                <Routes>
                  <Route path="/" element={<Home />} />
                  <Route path="/locations" element={<Locations />} />
                  <Route path="/forecast" element={<Forecast />} />
                  <Route path="/references" element={<References />} />
                </Routes>
                <BottomNav />
              </div>
            </div>
          </BrowserRouter>
        </TargetSpeciesProvider>
      </PreferencesProvider>
    </FavouritesProvider>
  )
}

export default App


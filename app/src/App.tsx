import { BrowserRouter, Routes, Route } from 'react-router-dom'
import Home from './pages/Home'
import Locations from './pages/Locations'
import Forecast from './pages/Forecast'
import References from './pages/References'
import { FavouritesProvider } from './contexts/FavouritesContext'
import BottomNav from './components/BottomNav'

function App() {
  return (
    <FavouritesProvider>
      <BrowserRouter>
        <div className="min-h-screen pb-16">
          <Routes>
            <Route path="/" element={<Home />} />
            <Route path="/locations" element={<Locations />} />
            <Route path="/forecast/:locationId" element={<Forecast />} />
            <Route path="/references" element={<References />} />
          </Routes>
          <BottomNav />
        </div>
      </BrowserRouter>
    </FavouritesProvider>
  )
}

export default App


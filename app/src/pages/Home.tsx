import { Link } from 'react-router-dom'

export default function Home() {
  return (
    <div className="container mx-auto px-4 py-6">
      <header className="mb-6">
        <h1 className="text-3xl font-bold text-primary-600">FishingInsights</h1>
        <p className="text-gray-600 mt-2">Fishing forecasts for Victorian anglers</p>
      </header>

      <div className="space-y-4">
        <div className="card">
          <h2 className="text-xl font-semibold mb-2">Today's Best</h2>
          <p className="text-gray-600">Loading...</p>
        </div>

        <div className="card">
          <h2 className="text-xl font-semibold mb-2">Favourites</h2>
          <p className="text-gray-600">No favourites yet</p>
        </div>

        <Link to="/locations" className="btn-primary block text-center">
          Browse Locations
        </Link>
      </div>
    </div>
  )
}


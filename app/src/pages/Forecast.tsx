import { useParams, useSearchParams } from 'react-router-dom'

export default function Forecast() {
  const { locationId } = useParams()
  const [searchParams] = useSearchParams()
  const lat = searchParams.get('lat')
  const lng = searchParams.get('lng')
  
  return (
    <div className="container mx-auto px-4 py-6">
      <header className="mb-6">
        <h1 className="text-2xl font-bold">Forecast</h1>
        {lat && lng && (
          <p className="text-gray-600">Location: {lat}, {lng}</p>
        )}
      </header>
      <p className="text-gray-600">Forecast data coming soon...</p>
    </div>
  )
}


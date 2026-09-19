
import { ImporterWidget } from './components/ImporterWidget';

function App() {
  return (
    <div style={{ padding: '20px' }}>
      <header style={{ padding: '20px 40px', display: 'flex', alignItems: 'center', gap: '12px' }}>
        <div style={{ width: '32px', height: '32px', background: 'var(--accent-primary)', borderRadius: '8px', boxShadow: '0 0 15px var(--accent-primary-glow)' }}></div>
        <h1 style={{ margin: 0, fontSize: '1.25rem', letterSpacing: '0.05em', color: '#fff' }}>ImportPilot</h1>
      </header>
      
      <main style={{ padding: '40px' }}>
        <ImporterWidget />
      </main>
    </div>
  );
}

export default App;

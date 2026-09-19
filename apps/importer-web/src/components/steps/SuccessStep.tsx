import React from 'react';
import { Check } from 'lucide-react';

export const SuccessStep: React.FC = () => {
  return (
    <div className="success-container">
      <div className="success-icon-wrapper">
        <Check size={40} />
      </div>
      <div>
        <h2 style={{ color: 'var(--text-primary)', marginBottom: '8px', fontSize: '1.5rem' }}>Import Complete!</h2>
        <p style={{ color: 'var(--text-secondary)' }}>
          Successfully imported <strong>354</strong> rows into your database.
        </p>
      </div>
    </div>
  );
};

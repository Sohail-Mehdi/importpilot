import React from 'react';
import { ArrowRight } from 'lucide-react';

export const MappingStep: React.FC = () => {
  const mockColumns = ['first_name', 'last_name', 'email_address', 'phone'];
  const targetFields = ['firstName', 'lastName', 'email', 'phone', 'company'];

  return (
    <div className="mapping-container">
      <p style={{ color: 'var(--text-secondary)', marginBottom: '16px' }}>
        Match the columns from your uploaded file to the destination fields.
      </p>
      
      {mockColumns.map((col, idx) => (
        <div key={idx} className="mapping-row">
          <div className="mapping-col">
            <span className="mapping-label">Your File Column</span>
            <span className="mapping-value">{col}</span>
          </div>
          <ArrowRight className="mapping-icon" size={20} />
          <div className="mapping-col">
            <span className="mapping-label">Destination Field</span>
            <select className="mapping-select" defaultValue={targetFields[idx] || ''}>
              <option value="">-- Ignore Column --</option>
              {targetFields.map((field) => (
                <option key={field} value={field}>{field}</option>
              ))}
            </select>
          </div>
        </div>
      ))}
    </div>
  );
};

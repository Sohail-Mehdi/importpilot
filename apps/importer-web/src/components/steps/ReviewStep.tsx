import React from 'react';
import { AlertCircle } from 'lucide-react';

export const ReviewStep: React.FC = () => {
  return (
    <div className="review-container">
      <div style={{ display: 'flex', gap: '12px', alignItems: 'center', marginBottom: '24px', padding: '16px', background: 'rgba(239, 68, 68, 0.1)', borderRadius: 'var(--border-radius-md)', border: '1px solid rgba(239, 68, 68, 0.2)' }}>
        <AlertCircle color="var(--accent-error)" />
        <div>
          <h4 style={{ color: '#f8fafc', margin: 0 }}>2 rows need your attention</h4>
          <p style={{ color: '#fca5a5', margin: 0, fontSize: '0.9rem' }}>Please fix the errors below before continuing.</p>
        </div>
      </div>

      <div className="review-table-container">
        <table className="review-table">
          <thead>
            <tr>
              <th>Row</th>
              <th>First Name</th>
              <th>Last Name</th>
              <th>Email</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>1</td>
              <td>John</td>
              <td>Doe</td>
              <td>john.doe@example.com</td>
            </tr>
            <tr>
              <td>2</td>
              <td>Jane</td>
              <td>Smith</td>
              <td className="error-cell">invalid-email-format</td>
            </tr>
            <tr>
              <td>3</td>
              <td>Alice</td>
              <td className="error-cell"><em>Missing required</em></td>
              <td>alice@example.com</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  );
};

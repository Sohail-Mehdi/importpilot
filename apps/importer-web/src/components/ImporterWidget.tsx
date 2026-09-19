import React, { useState } from 'react';
import { UploadStep } from './steps/UploadStep';
import { MappingStep } from './steps/MappingStep';
import { ReviewStep } from './steps/ReviewStep';
import { SuccessStep } from './steps/SuccessStep';
import clsx from 'clsx';
import './ImporterWidget.css';

const steps = ['Upload', 'Map Columns', 'Review', 'Complete'];

export const ImporterWidget: React.FC = () => {
  const [currentStep, setCurrentStep] = useState(0);

  const handleNext = () => {
    if (currentStep < steps.length - 1) {
      setCurrentStep(curr => curr + 1);
    }
  };

  const handleBack = () => {
    if (currentStep > 0) {
      setCurrentStep(curr => curr - 1);
    }
  };

  const renderStep = () => {
    switch (currentStep) {
      case 0: return <UploadStep />;
      case 1: return <MappingStep />;
      case 2: return <ReviewStep />;
      case 3: return <SuccessStep />;
      default: return null;
    }
  };

  return (
    <div className="importer-widget glass-panel">
      <div className="importer-header">
        <h2>Import Data</h2>
        <div className="step-indicator">
          {steps.map((step, idx) => (
            <React.Fragment key={step}>
              <div 
                className={clsx('step-dot', {
                  'active': idx === currentStep,
                  'completed': idx < currentStep
                })} 
                title={step}
              />
              {idx < steps.length - 1 && (
                <div style={{ width: '20px', height: '1px', background: 'var(--border-color)' }} />
              )}
            </React.Fragment>
          ))}
        </div>
      </div>
      
      <div className="importer-content">
        {renderStep()}
      </div>

      {currentStep < 3 && (
        <div className="importer-footer">
          {currentStep > 0 && (
            <button className="btn btn-secondary" onClick={handleBack}>
              Back
            </button>
          )}
          <button className="btn btn-primary" onClick={handleNext}>
            {currentStep === 2 ? 'Confirm Import' : 'Continue'}
          </button>
        </div>
      )}
    </div>
  );
};
